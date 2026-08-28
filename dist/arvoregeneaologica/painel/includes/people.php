<?php

require_once __DIR__ . '/upload.php';

function genealogy_h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function genealogy_table_ok(PDO $pdo): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $pdo->query('SELECT 1 FROM genealogy_people LIMIT 1');
        $ok = true;
    } catch (Throwable $e) {
        $ok = false;
    }
    return $ok;
}

function genealogy_calc_age(?string $birthDate, ?string $deathDate, ?int $birthYearOnly): array
{
    if ($birthDate) {
        try {
            $start = new DateTime($birthDate);
            $end = $deathDate ? new DateTime($deathDate) : new DateTime('today');
            $diff = $start->diff($end);
            $age = (int) $diff->y;
            $label = $deathDate
                ? "{$age} anos (ao falecer)"
                : "{$age} anos";
            return ['text' => $label, 'sort' => $age];
        } catch (Throwable $e) {
            return ['text' => '', 'sort' => null];
        }
    }
    if ($birthYearOnly) {
        $approx = (int) date('Y') - $birthYearOnly;
        return ['text' => "Nasc. ~{$birthYearOnly}", 'sort' => $approx];
    }
    return ['text' => '', 'sort' => null];
}

function genealogy_format_dates(?string $birthDate, ?string $deathDate, ?int $birthYearOnly): string
{
    if ($birthDate) {
        $b = date('d/m/Y', strtotime($birthDate));
        if ($deathDate) {
            return $b . ' – ' . date('d/m/Y', strtotime($deathDate));
        }
        return $b;
    }
    if ($birthYearOnly) {
        return '~' . $birthYearOnly;
    }
    return '';
}

function genealogy_get_person(PDO $pdo, int $id): ?array
{
    $st = $pdo->prepare('SELECT * FROM genealogy_people WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function genealogy_search_people(PDO $pdo, string $q = '', ?int $excludeId = null, int $limit = 50): array
{
    $q = trim($q);
    $sql = 'SELECT id, full_name, birth_date, death_date, birth_year_only FROM genealogy_people WHERE 1=1';
    $params = [];
    if ($q !== '') {
        $sql .= ' AND full_name LIKE ?';
        $params[] = '%' . $q . '%';
    }
    if ($excludeId) {
        $sql .= ' AND id != ?';
        $params[] = $excludeId;
    }
    $sql .= ' ORDER BY full_name ASC LIMIT ' . (int) $limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function genealogy_get_relations(PDO $pdo, int $personId): array
{
    $st = $pdo->prepare(
        'SELECT r.relation_type, r.related_id, p.full_name
         FROM genealogy_relations r
         JOIN genealogy_people p ON p.id = r.related_id
         WHERE r.person_id = ?
         ORDER BY p.full_name ASC'
    );
    $st->execute([$personId]);
    $out = ['father' => [], 'mother' => [], 'spouse' => [], 'child' => []];
    foreach ($st->fetchAll() as $row) {
        $type = $row['relation_type'];
        if (isset($out[$type])) {
            $out[$type][] = [
                'id' => (int) $row['related_id'],
                'full_name' => $row['full_name'],
            ];
        }
    }
    return $out;
}

function genealogy_relation_summary(PDO $pdo, int $personId): string
{
    $rel = genealogy_get_relations($pdo, $personId);
    $parts = [];
    if ($rel['father']) {
        $parts[] = 'Pai: ' . $rel['father'][0]['full_name'];
    }
    if ($rel['mother']) {
        $parts[] = 'Mãe: ' . $rel['mother'][0]['full_name'];
    }
    if ($rel['spouse']) {
        $names = array_map(fn ($s) => $s['full_name'], $rel['spouse']);
        $parts[] = 'Cônjuge: ' . implode(', ', $names);
    }
    if ($rel['child']) {
        $parts[] = count($rel['child']) . ' filho(s)';
    }
    return $parts ? implode(' · ', $parts) : 'Sem vínculos';
}

function genealogy_remove_relations_of_type(PDO $pdo, int $personId, string $type): void
{
    $st = $pdo->prepare('SELECT related_id FROM genealogy_relations WHERE person_id = ? AND relation_type = ?');
    $st->execute([$personId, $type]);
    $related = $st->fetchAll(PDO::FETCH_COLUMN);

    $pdo->prepare('DELETE FROM genealogy_relations WHERE person_id = ? AND relation_type = ?')
        ->execute([$personId, $type]);

    $mirror = genealogy_mirror_type($type);
    if ($mirror) {
        $del = $pdo->prepare('DELETE FROM genealogy_relations WHERE person_id = ? AND related_id = ? AND relation_type = ?');
        foreach ($related as $rid) {
            $del->execute([(int) $rid, $personId, $mirror]);
        }
    }
}

function genealogy_mirror_type(string $type): ?string
{
    return match ($type) {
        'father', 'mother' => 'child',
        'child' => null,
        'spouse' => 'spouse',
        default => null,
    };
}

function genealogy_add_relation(PDO $pdo, int $personId, int $relatedId, string $type): void
{
    if ($personId === $relatedId) {
        return;
    }

    $ins = $pdo->prepare(
        'INSERT IGNORE INTO genealogy_relations (person_id, related_id, relation_type) VALUES (?, ?, ?)'
    );
    $ins->execute([$personId, $relatedId, $type]);

    if ($type === 'father' || $type === 'mother') {
        $ins->execute([$relatedId, $personId, 'child']);
    } elseif ($type === 'child') {
        // child relation added from parent side — mirror handled when setting father/mother
    } elseif ($type === 'spouse') {
        $ins->execute([$relatedId, $personId, 'spouse']);
    }
}

function genealogy_set_single_relation(PDO $pdo, int $personId, ?int $relatedId, string $type): void
{
    genealogy_remove_relations_of_type($pdo, $personId, $type);
    if ($relatedId && $relatedId > 0) {
        genealogy_add_relation($pdo, $personId, $relatedId, $type);
    }
}

function genealogy_add_child(PDO $pdo, int $parentId, int $childId): void
{
    if ($parentId === $childId) {
        return;
    }
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO genealogy_relations (person_id, related_id, relation_type) VALUES (?, ?, ?)'
    );
    $ins->execute([$parentId, $childId, 'child']);

    $parent = genealogy_get_person($pdo, $parentId);
    $gender = $parent['gender'] ?? null;
    if ($gender === 'M') {
        genealogy_set_single_relation($pdo, $childId, $parentId, 'father');
    } elseif ($gender === 'F') {
        genealogy_set_single_relation($pdo, $childId, $parentId, 'mother');
    }
}

function genealogy_unlink_child(PDO $pdo, int $parentId, int $childId): void
{
    $pdo->prepare('DELETE FROM genealogy_relations WHERE person_id = ? AND related_id = ? AND relation_type = ?')
        ->execute([$parentId, $childId, 'child']);

    $parent = genealogy_get_person($pdo, $parentId);
    $gender = $parent['gender'] ?? null;
    if ($gender === 'M') {
        $pdo->prepare('DELETE FROM genealogy_relations WHERE person_id = ? AND related_id = ? AND relation_type = ?')
            ->execute([$childId, $parentId, 'father']);
    } elseif ($gender === 'F') {
        $pdo->prepare('DELETE FROM genealogy_relations WHERE person_id = ? AND related_id = ? AND relation_type = ?')
            ->execute([$childId, $parentId, 'mother']);
    }
}

function genealogy_save_person(PDO $pdo, array $data, ?int $id = null): int
{
    $fullName = trim($data['full_name'] ?? '');
    if ($fullName === '') {
        throw InvalidArgumentException('Nome completo é obrigatório.');
    }

    $birthDate = trim($data['birth_date'] ?? '') ?: null;
    $deathDate = trim($data['death_date'] ?? '') ?: null;
    $birthYear = trim($data['birth_year_only'] ?? '') !== '' ? (int) $data['birth_year_only'] : null;
    $gender = in_array($data['gender'] ?? '', ['M', 'F', 'O'], true) ? $data['gender'] : null;
    $notes = trim($data['notes'] ?? '') ?: null;

    if ($birthDate) {
        $birthYear = null;
    }

    if ($id) {
        $pdo->prepare(
            'UPDATE genealogy_people SET full_name=?, birth_date=?, death_date=?, birth_year_only=?, gender=?, notes=? WHERE id=?'
        )->execute([$fullName, $birthDate, $deathDate, $birthYear, $gender, $notes, $id]);
        return $id;
    }

    $pdo->prepare(
        'INSERT INTO genealogy_people (full_name, birth_date, death_date, birth_year_only, gender, notes) VALUES (?,?,?,?,?,?)'
    )->execute([$fullName, $birthDate, $deathDate, $birthYear, $gender, $notes]);
    return (int) $pdo->lastInsertId();
}

function genealogy_save_relations_from_post(PDO $pdo, int $personId, array $post): void
{
    $fatherId = (int) ($post['father_id'] ?? 0) ?: null;
    $motherId = (int) ($post['mother_id'] ?? 0) ?: null;
    genealogy_set_single_relation($pdo, $personId, $fatherId, 'father');
    genealogy_set_single_relation($pdo, $personId, $motherId, 'mother');

    $spouseIds = array_filter(array_map('intval', (array) ($post['spouse_ids'] ?? [])));
    genealogy_remove_relations_of_type($pdo, $personId, 'spouse');
    foreach (array_unique($spouseIds) as $sid) {
        if ($sid && $sid !== $personId) {
            genealogy_add_relation($pdo, $personId, $sid, 'spouse');
        }
    }

    $childIds = array_filter(array_map('intval', (array) ($post['child_ids'] ?? [])));
    $currentChildren = genealogy_get_relations($pdo, $personId)['child'];
    $currentIds = array_map(fn ($c) => $c['id'], $currentChildren);

    foreach ($currentIds as $cid) {
        if (!in_array($cid, $childIds, true)) {
            genealogy_unlink_child($pdo, $personId, $cid);
        }
    }
    foreach (array_unique($childIds) as $cid) {
        if ($cid && $cid !== $personId && !in_array($cid, $currentIds, true)) {
            genealogy_add_child($pdo, $personId, $cid);
        }
    }
}

function genealogy_person_has_parents(PDO $pdo, int $personId): bool
{
    $rel = genealogy_get_relations($pdo, $personId);
    return !empty($rel['father']) || !empty($rel['mother']);
}

function genealogy_person_to_public(array $row): array
{
    $birthYear = $row['birth_year_only'] ? (int) $row['birth_year_only'] : null;
    $age = genealogy_calc_age($row['birth_date'] ?? null, $row['death_date'] ?? null, $birthYear);
    $dates = genealogy_format_dates($row['birth_date'] ?? null, $row['death_date'] ?? null, $birthYear);

    $parts = preg_split('/\s+/', trim($row['full_name']), 2);
    $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1] ?? '', 0, 1));

    return [
        'id' => (int) $row['id'],
        'full_name' => $row['full_name'],
        'initials' => $initials ?: '?',
        'photo' => genealogy_photo_url($row['photo_path'] ?? null),
        'dates' => $dates,
        'age' => $age['text'],
        'gender' => $row['gender'] ?? null,
        'notes' => $row['notes'] ?? null,
    ];
}

function genealogy_export_tree_data(PDO $pdo): array
{
    $peopleRows = $pdo->query(
        'SELECT id, full_name, birth_date, death_date, birth_year_only, gender, notes, photo_path
         FROM genealogy_people ORDER BY full_name ASC'
    )->fetchAll();

    $people = [];
    foreach ($peopleRows as $row) {
        $people[] = genealogy_person_to_public($row);
    }

    $relRows = $pdo->query(
        'SELECT person_id, related_id, relation_type FROM genealogy_relations'
    )->fetchAll();

    $parentLinks = [];
    $spouseLinks = [];
    $seenParent = [];

    foreach ($relRows as $r) {
        $pid = (int) $r['person_id'];
        $rid = (int) $r['related_id'];
        $type = $r['relation_type'];

        if ($type === 'father' || $type === 'mother') {
            $key = $rid . '-' . $pid;
            if (!isset($seenParent[$key])) {
                $seenParent[$key] = true;
                $parentLinks[] = ['parent' => $rid, 'child' => $pid];
            }
        } elseif ($type === 'child') {
            $key = $pid . '-' . $rid;
            if (!isset($seenParent[$key])) {
                $seenParent[$key] = true;
                $parentLinks[] = ['parent' => $pid, 'child' => $rid];
            }
        } elseif ($type === 'spouse' && $pid < $rid) {
            $spouseLinks[] = ['a' => $pid, 'b' => $rid];
        }
    }

    return [
        'people' => $people,
        'parent_links' => $parentLinks,
        'spouse_links' => $spouseLinks,
        'count' => count($people),
    ];
}
