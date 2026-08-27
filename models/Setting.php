<?php
require_once __DIR__ . '/../core/Model.php';

class Setting extends Model
{
    private static array $defaults = [
        'maintenance_mode' => '0',
        'maintenance_started_at' => '',
        'maintenance_last_admin_activity_at' => '',
        'shop_locked' => '0',
        'registration_mode' => 'open',
    ];

    public static function get(string $name, ?string $default = null): ?string
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            SELECT value
            FROM settings
            WHERE name = ?
            LIMIT 1
        ");
        $stmt->execute([$name]);

        $value = $stmt->fetchColumn();

        if ($value === false) {
            return $default ?? (self::$defaults[$name] ?? null);
        }

        return (string)$value;
    }

    public static function getBool(string $name, bool $default = false): bool
    {
        $value = self::get($name, $default ? '1' : '0');
        return in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
    }

    public static function set(string $name, string $value): bool
    {
        $db = self::getDB();

        $stmt = $db->prepare("
            INSERT INTO settings (name, value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
                value = VALUES(value)
        ");

        return $stmt->execute([$name, $value]);
    }

    public static function setMany(array $settings): bool
    {
        $db = self::getDB();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("
                INSERT INTO settings (name, value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE
                    value = VALUES(value)
            ");

            foreach ($settings as $name => $value) {
                $stmt->execute([(string)$name, (string)$value]);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function all(): array
    {
        $db = self::getDB();

        $stmt = $db->query("
            SELECT name, value, updated_at
            FROM settings
            ORDER BY name ASC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $settings = self::$defaults;

        foreach ($rows as $row) {
            $settings[$row['name']] = $row['value'];
        }

        return $settings;
    }

    public static function getAppSettings(): array
    {
        $settings = self::all();

        $registrationMode = $settings['registration_mode'] ?? 'open';
        if (!in_array($registrationMode, ['open', 'approval_required'], true)) {
            $registrationMode = 'open';
        }

        return [
            'maintenance_mode' => in_array((string)($settings['maintenance_mode'] ?? '0'), ['1', 'true', 'on'], true),
            'maintenance_started_at' => (string)($settings['maintenance_started_at'] ?? ''),
            'maintenance_last_admin_activity_at' => (string)($settings['maintenance_last_admin_activity_at'] ?? ''),
            'shop_locked' => in_array((string)($settings['shop_locked'] ?? '0'), ['1', 'true', 'on'], true),
            'registration_mode' => $registrationMode,
        ];
    }
}
