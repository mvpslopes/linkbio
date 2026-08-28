<?php
require_once __DIR__ . '/db.php';

const LOYALTY_GOAL = 10;
const LOYALTY_REWARD_DAYS = 30;

function loyalty_slug(): string
{
    return defined('PRISCILA_SLUG') ? PRISCILA_SLUG : 'priscilaramos';
}

if (!function_exists('loyalty_h')) {
    function loyalty_h(?string $s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function loyalty_table_ok(PDO $pdo): bool
{
    $tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);
    return in_array('loyalty_clients', $tables, true) && in_array('loyalty_stamps', $tables, true);
}

function loyalty_services(): array
{
    return [
        'Pé e mão',
        'Sobrancelha',
        'Buço',
        'Buço e nariz',
        'Axila',
        'Meia perna',
        'Virilha',
        'Perna inteira',
    ];
}

function loyalty_normalize_phone(string $raw): string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '00')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 10 || strlen($d) === 11) {
        $d = '55' . $d;
    }
    return $d;
}

function loyalty_phone_valid(string $phone): bool
{
    return (bool) preg_match('/^55\d{10,11}$/', $phone);
}

function loyalty_format_phone(string $phone): string
{
    if (preg_match('/^55(\d{2})(\d{5})(\d{4})$/', $phone, $m)) {
        return "+55 ({$m[1]}) {$m[2]}-{$m[3]}";
    }
    if (preg_match('/^55(\d{2})(\d{4})(\d{4})$/', $phone, $m)) {
        return "+55 ({$m[1]}) {$m[2]}-{$m[3]}";
    }
    return $phone;
}

function loyalty_remaining(array $client): int
{
    if (!empty($client['reward_available'])) {
        return 0;
    }
    return max(0, LOYALTY_GOAL - (int) $client['stamps_count']);
}

function loyalty_wa_url(string $phone, string $msg): string
{
    return 'https://wa.me/' . preg_replace('/\D+/', '', $phone)
        . '?text=' . rawurlencode($msg);
}

function loyalty_first_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '';
    }
    $parts = preg_split('/\s+/', $name) ?: [$name];
    return $parts[0];
}

function loyalty_confirm_message(array $client): string
{
    $nome = loyalty_first_name((string) $client['name']) ?: 'oi';
    $n = (int) $client['stamps_count'];

    if (!empty($client['reward_available'])) {
        return "Oi {$nome}! Você completou 10/10 no cartão fidelidade da Priscila Ramos 🎉 O próximo atendimento express é por nossa conta. Válido por " . LOYALTY_REWARD_DAYS . " dias.";
    }

    $rest = max(0, LOYALTY_GOAL - $n);
    $falta = $rest === 1 ? '1 atendimento' : $rest . ' atendimentos';
    return "Oi {$nome}! Seu cartão fidelidade da Priscila Ramos está em {$n}/10. Faltam {$falta} para um serviço por nossa conta.";
}

function loyalty_redeem_message(array $client): string
{
    $nome = loyalty_first_name((string) $client['name']) ?: 'oi';
    return "Oi {$nome}! Brinde resgatado. Seu cartão da Priscila Ramos zerou e começa um ciclo novo. Obrigada pela confiança!";
}

function loyalty_find_client(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM loyalty_clients WHERE id = ? AND page_slug = ? LIMIT 1');
    $st->execute([$id, loyalty_slug()]);
    $row = $st->fetch() ?: null;
    return $row ?: null;
}

function loyalty_find_by_phone(PDO $pdo, string $phone): ?array
{
    $st = $pdo->prepare('SELECT * FROM loyalty_clients WHERE page_slug = ? AND phone = ? LIMIT 1');
    $st->execute([loyalty_slug(), $phone]);
    $row = $st->fetch() ?: null;
    return $row ?: null;
}

function loyalty_add_stamp(PDO $pdo, array $client, string $service, int $userId): array
{
    if (!empty($client['reward_available'])) {
        throw new RuntimeException('Resgate o brinde antes de marcar um novo selo.');
    }
    if (!in_array($service, loyalty_services(), true)) {
        throw new RuntimeException('Escolha um serviço da lista.');
    }

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM loyalty_clients WHERE id = ? AND page_slug = ? FOR UPDATE');
        $st->execute([(int) $client['id'], loyalty_slug()]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Cliente não encontrada.');
        }
        if (!empty($row['reward_available'])) {
            throw new RuntimeException('Resgate o brinde antes de marcar um novo selo.');
        }
        if ((int) $row['stamps_count'] >= LOYALTY_GOAL) {
            throw new RuntimeException('Cartão completo. Resgate o brinde para recomeçar.');
        }

        $next = (int) $row['stamps_count'] + 1;
        $reward = $next >= LOYALTY_GOAL ? 1 : 0;
        $expires = $reward ? date('Y-m-d H:i:s', time() + LOYALTY_REWARD_DAYS * 86400) : null;

        $pdo->prepare(
            'INSERT INTO loyalty_stamps (client_id, kind, service, created_by) VALUES (?, \'stamp\', ?, ?)'
        )->execute([(int) $row['id'], $service, $userId]);

        $pdo->prepare(
            'UPDATE loyalty_clients SET stamps_count = ?, reward_available = ?, reward_expires_at = ? WHERE id = ?'
        )->execute([$next, $reward, $expires, (int) $row['id']]);

        $pdo->commit();
        return loyalty_find_client($pdo, (int) $row['id']) ?? $row;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function loyalty_redeem(PDO $pdo, array $client, int $userId): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM loyalty_clients WHERE id = ? AND page_slug = ? FOR UPDATE');
        $st->execute([(int) $client['id'], loyalty_slug()]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Cliente não encontrada.');
        }
        if (empty($row['reward_available'])) {
            throw new RuntimeException('Não há brinde para resgatar.');
        }

        $pdo->prepare(
            'INSERT INTO loyalty_stamps (client_id, kind, service, created_by) VALUES (?, \'reward\', ?, ?)'
        )->execute([(int) $row['id'], 'Brinde resgatado', $userId]);

        $earned = (int) $row['rewards_earned'] + 1;
        $pdo->prepare(
            'UPDATE loyalty_clients
             SET stamps_count = 0, reward_available = 0, reward_expires_at = NULL, rewards_earned = ?
             WHERE id = ?'
        )->execute([$earned, (int) $row['id']]);

        $pdo->commit();
        return loyalty_find_client($pdo, (int) $row['id']) ?? $row;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function loyalty_undo_last(PDO $pdo, array $client, int $userId): array
{
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT * FROM loyalty_clients WHERE id = ? AND page_slug = ? FOR UPDATE');
        $st->execute([(int) $client['id'], loyalty_slug()]);
        $row = $st->fetch();
        if (!$row) {
            throw new RuntimeException('Cliente não encontrada.');
        }

        $last = $pdo->prepare(
            "SELECT id FROM loyalty_stamps WHERE client_id = ? AND kind = 'stamp' ORDER BY id DESC LIMIT 1"
        );
        $last->execute([(int) $row['id']]);
        $stampId = $last->fetchColumn();
        if (!$stampId) {
            throw new RuntimeException('Não há selo para desfazer.');
        }

        $pdo->prepare('DELETE FROM loyalty_stamps WHERE id = ?')->execute([(int) $stampId]);
        $pdo->prepare(
            'INSERT INTO loyalty_stamps (client_id, kind, service, created_by) VALUES (?, \'undo\', ?, ?)'
        )->execute([(int) $row['id'], 'Selo desfeito', $userId]);

        $next = max(0, (int) $row['stamps_count'] - 1);
        $pdo->prepare(
            'UPDATE loyalty_clients SET stamps_count = ?, reward_available = 0, reward_expires_at = NULL WHERE id = ?'
        )->execute([$next, (int) $row['id']]);

        $pdo->commit();
        return loyalty_find_client($pdo, (int) $row['id']) ?? $row;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function loyalty_kind_label(string $kind): string
{
    return match ($kind) {
        'stamp' => 'Selo',
        'reward' => 'Brinde',
        'undo' => 'Desfeito',
        default => $kind,
    };
}
