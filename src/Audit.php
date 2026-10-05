<?php
declare(strict_types=1);

namespace App;

/** سجل التدقيق: من فعل ماذا ومتى ومن أي عنوان IP */
final class Audit
{
    public static function record(string $action, ?string $entityType = null, ?int $entityId = null, array $meta = []): void
    {
        Database::instance()->insert('audit_logs', [
            'user_id'     => Auth::instance()->id(),
            'action'      => $action,
            'entity_type' => $entityType,
            'entity_id'   => $entityId,
            'meta'        => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
            'ip'          => Security::ip(),
            'user_agent'  => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 190),
        ]);
    }
}
