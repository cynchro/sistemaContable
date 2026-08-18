<?php

namespace App\Modules\Compartido\Sige;

/**
 * Datos de un contribuyente traídos del SIGE (sistemaCuarto) por CUIT, vía
 * GET /api/sync/contribuyente/{cuit}. Solo identidad/CRM — el SIGE no expone
 * credenciales AFIP por este endpoint.
 */
final class ContribuyenteSige
{
    public function __construct(
        public readonly int $personaId,
        public readonly string $cuit,
        public readonly ?string $nombre,
        public readonly ?string $tipoPersona,
        public readonly ?string $contacto,
        public readonly ?string $telefono,
        public readonly ?string $email,
        public readonly ?string $inscripcion,
        public readonly ?string $contabilidad,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            personaId: (int) $data['persona_id'],
            cuit: (string) $data['cuit'],
            nombre: $data['nombre'] ?? null,
            tipoPersona: $data['tipo_persona'] ?? null,
            contacto: $data['contacto'] ?? null,
            telefono: $data['telefono'] ?? null,
            email: $data['email'] ?? null,
            inscripcion: $data['inscripcion'] ?? null,
            contabilidad: $data['contabilidad'] ?? null,
        );
    }
}
