// SIGTI - Puente de WhatsApp (vinculo NO oficial por codigo QR, como WhatsApp Web)
// ============================================================
// Esto NO es PHP y no corre dentro de Apache: es un servicio aparte en
// Node.js que debe quedar siempre encendido. core/WhatsApp.php (en SIGTI)
// le habla por HTTP para pedirle el estado (vinculado o no, el QR para
// vincular) y para pedirle que mande mensajes, con un token compartido
// para que nadie mas lo use.
//
// Primer uso:
//   1) npm install
//   2) copie .env.example a .env y complete PUERTO y TOKEN
//      (TOKEN debe ser EXACTAMENTE el mismo que pondra en SIGTI, en
//       Configuracion > WhatsApp)
//   3) npm start
//   4) en SIGTI, vaya a Configuracion > WhatsApp, pegue la direccion
//      (ej: http://127.0.0.1:3300) y el token, guarde, y ahi mismo
//      aparecera el codigo QR: escanéelo desde WhatsApp > Dispositivos
//      vinculados > Vincular un dispositivo.
//
// La sesion vinculada se guarda en la carpeta "sesion/" (no se sube a
// ningun lado). Si se borra esa carpeta, o si alguien cierra la sesion
// desde el celular, hay que volver a escanear el QR.
// ============================================================

import 'dotenv/config';
import express from 'express';
import qrcode from 'qrcode';
import pino from 'pino';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';
import makeWASocket, { useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PUERTO = parseInt(process.env.PUERTO || '3300', 10);
const HOST = process.env.HOST || '127.0.0.1';
const TOKEN = process.env.TOKEN || '';
const CARPETA_SESION = path.join(__dirname, 'sesion');

if (!TOKEN) {
    console.error('Falta TOKEN en .env. Copie .env.example a .env y complete PUERTO, TOKEN y HOST antes de iniciar (npm start).');
    process.exit(1);
}

const logger = pino({ level: process.env.LOG_LEVEL || 'warn' });

let sock = null;
let qrActual = null;          // data URL del QR pendiente (null si ya esta vinculado o aun no llego)
let numeroVinculado = null;   // numero vinculado (solo digitos), o null si no hay sesion
let reintentando = false;

async function iniciar() {
    const { state, saveCreds } = await useMultiFileAuthState(CARPETA_SESION);

    sock = makeWASocket({ auth: state, logger });
    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (u) => {
        const { connection, lastDisconnect, qr } = u;

        if (qr) {
            try {
                qrActual = await qrcode.toDataURL(qr);
            } catch (e) {
                console.error('No se pudo generar la imagen del QR:', e);
            }
            numeroVinculado = null;
        }

        if (connection === 'open') {
            qrActual = null;
            numeroVinculado = sock?.user?.id ? sock.user.id.split(':')[0].split('@')[0] : null;
            console.log('WhatsApp vinculado:', numeroVinculado || '(numero no disponible)');
        }

        if (connection === 'close') {
            const codigo = lastDisconnect?.error?.output?.statusCode;
            const cerroSesion = codigo === DisconnectReason.loggedOut;
            if (cerroSesion) {
                qrActual = null;
                numeroVinculado = null;
                console.log('Sesión de WhatsApp cerrada (desde el celular o por "Desvincular"). Hace falta un nuevo QR.');
            } else if (!reintentando) {
                reintentando = true;
                console.log('Conexión de WhatsApp perdida, reintentando en 3 segundos…');
                setTimeout(() => { reintentando = false; iniciar().catch((e) => console.error('No se pudo reconectar:', e)); }, 3000);
            }
        }
    });
}

iniciar().catch((e) => console.error('No se pudo iniciar WhatsApp:', e));

// ---------------------------------------------------------------- servidor HTTP

const app = express();
app.use(express.json());

// Todo lo demas exige el token compartido con SIGTI.
app.use((req, res, next) => {
    const auth = req.headers['authorization'] || '';
    if (auth !== `Bearer ${TOKEN}`) return res.status(401).json({ error: 'Token inválido.' });
    next();
});

app.get('/estado', (req, res) => {
    res.json({ vinculado: !!numeroVinculado, numero: numeroVinculado, qr: qrActual });
});

app.post('/enviar', async (req, res) => {
    try {
        if (!sock || !numeroVinculado) {
            return res.status(409).json({ error: 'WhatsApp todavía no está vinculado. Escanee el código QR primero.' });
        }
        const numero = String(req.body?.numero || '').replace(/\D+/g, '');
        const mensaje = String(req.body?.mensaje || '').trim();
        if (!numero) return res.status(400).json({ error: 'Falta el número.' });
        if (!mensaje) return res.status(400).json({ error: 'Falta el mensaje.' });

        const jid = `${numero}@s.whatsapp.net`;
        let destino = jid;
        try {
            const r = await sock.onWhatsApp(jid);
            if (r && r[0]) {
                if (!r[0].exists) return res.status(422).json({ error: `El número ${numero} no tiene WhatsApp.` });
                destino = r[0].jid || jid;
            }
        } catch (e) {
            // si onWhatsApp falla (red lenta, etc.) igual se intenta enviar al jid armado a mano
        }

        await sock.sendMessage(destino, { text: mensaje });
        res.json({ ok: true });
    } catch (e) {
        console.error('Error al enviar:', e);
        res.status(500).json({ error: 'No se pudo enviar el mensaje: ' + (e?.message || String(e)) });
    }
});

app.post('/desvincular', async (req, res) => {
    try {
        if (sock) {
            try { await sock.logout(); } catch (e) { /* puede fallar si ya estaba caida la conexion: no importa */ }
        }
        qrActual = null;
        numeroVinculado = null;
        fs.rmSync(CARPETA_SESION, { recursive: true, force: true });
        setTimeout(() => { iniciar().catch((e) => console.error('No se pudo reiniciar tras desvincular:', e)); }, 500);
        res.json({ ok: true });
    } catch (e) {
        res.status(500).json({ error: 'No se pudo desvincular: ' + (e?.message || String(e)) });
    }
});

app.listen(PUERTO, HOST, () => {
    console.log(`Puente de WhatsApp escuchando en http://${HOST}:${PUERTO}`);
});
