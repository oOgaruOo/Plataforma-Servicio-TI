<?php
// ============================================================
// SIGTI - Validacion de datos del servidor
// ============================================================

class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];

    public function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data   = $data;
        $this->rules  = $rules;
        $this->labels = $labels;
    }

    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $rules, $labels);
        $v->ejecutar();
        return $v;
    }

    public function fails(): bool      { return !empty($this->errors); }
    public function passes(): bool     { return empty($this->errors); }
    public function errors(): array    { return $this->errors; }
    public function primero(): string  { return reset($this->errors) ?: ''; }

    private function ejecutar(): void
    {
        foreach ($this->rules as $campo => $reglasStr) {

            $valor   = $this->data[$campo] ?? null;
            $esVacio = ($valor === null || $valor === '' || $valor === []);
            $label   = $this->labels[$campo] ?? ucfirst(str_replace('_', ' ', $campo));

            $reglas = explode('|', $reglasStr);
            if (in_array('required', $reglas, true) && $esVacio) {
                $this->errors[$campo] = "El campo {$label} es obligatorio.";
                continue;
            }
            if ($esVacio) continue;

            foreach ($reglas as $regla) {
                [$nombre, $param] = array_pad(explode(':', $regla, 2), 2, null);
                $this->aplicarRegla($campo, $label, (string)$valor, $nombre, $param);
                if (isset($this->errors[$campo])) break;
            }
        }
    }

    private function aplicarRegla(string $campo, string $label, string $valor, string $regla, ?string $param): void
    {
        switch ($regla) {
            case 'email':
                if (!filter_var($valor, FILTER_VALIDATE_EMAIL))
                    $this->errors[$campo] = "El campo {$label} debe ser un correo valido.";
                break;
            case 'integer':
                if (filter_var($valor, FILTER_VALIDATE_INT) === false)
                    $this->errors[$campo] = "El campo {$label} debe ser un numero entero.";
                break;
            case 'numeric':
                if (!is_numeric($valor))
                    $this->errors[$campo] = "El campo {$label} debe ser numerico.";
                break;
            case 'min':
                if (is_numeric($valor) && (float)$valor < (float)$param)
                    $this->errors[$campo] = "El campo {$label} debe ser mayor o igual a {$param}.";
                break;
            case 'max':
                if (is_numeric($valor) && (float)$valor > (float)$param)
                    $this->errors[$campo] = "El campo {$label} no debe superar {$param}.";
                break;
            case 'minlen':
                if (mb_strlen($valor) < (int)$param)
                    $this->errors[$campo] = "El campo {$label} debe tener al menos {$param} caracteres.";
                break;
            case 'maxlen':
                if (mb_strlen($valor) > (int)$param)
                    $this->errors[$campo] = "El campo {$label} no debe superar {$param} caracteres.";
                break;
            case 'in':
                $opciones = array_map('trim', explode(',', (string)$param));
                if (!in_array($valor, $opciones, true))
                    $this->errors[$campo] = "El campo {$label} debe ser uno de: " . implode(', ', $opciones) . ".";
                break;
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $valor);
                if (!$d || $d->format('Y-m-d') !== $valor)
                    $this->errors[$campo] = "El campo {$label} debe ser una fecha valida (AAAA-MM-DD).";
                break;
            case 'datetime':
                $d = DateTime::createFromFormat('Y-m-d H:i:s', $valor);
                if (!$d)
                    $this->errors[$campo] = "El campo {$label} debe ser fecha y hora valida.";
                break;
            case 'dni':
                if (!preg_match('/^\d{8}$/', $valor))
                    $this->errors[$campo] = "El campo {$label} debe tener 8 digitos.";
                break;
            case 'telefono':
                if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $valor))
                    $this->errors[$campo] = "El campo {$label} debe ser un telefono valido.";
                break;
            case 'alpha_spaces':
                if (!preg_match('/^[\p{L}\s]+$/u', $valor))
                    $this->errors[$campo] = "El campo {$label} solo acepta letras.";
                break;
            case 'boolean':
                if (!in_array($valor, ['0','1',0,1,true,false], true))
                    $this->errors[$campo] = "El campo {$label} debe ser 0 o 1.";
                break;
        }
    }
}