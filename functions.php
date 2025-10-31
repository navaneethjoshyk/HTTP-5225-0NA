<?php


function clsStress($v){ $v = (float)$v; return ($v >= 8) ? 'bad' : (($v >= 6) ? 'warn' : 'ok'); }
function clsSleep($v){  $v = (float)$v; return ($v >= 8) ? 'ok' : (($v <= 4) ? 'bad' : ''); }
function clsHappy($v){  $v = (float)$v; return ($v >= 8) ? 'ok' : (($v <= 4) ? 'bad' : ''); }

function getDistinctPlatforms(mysqli $conn): array {
    $platforms = [];
    $sql = "
        SELECT DISTINCT p FROM (
            SELECT `Social_Media_Platform` AS p FROM `mental_health_male`
            UNION
            SELECT `Social_Media_Platform` AS p FROM `mental_health_female`
        ) x ORDER BY p
    ";
    $res = $conn->query($sql);
    while ($r = $res->fetch_assoc()) $platforms[] = $r['p'];
    return $platforms;
}

function validOrderColsPairs(): array {
    return [
        'platform'       => 'platform',
        'male_age'       => 'male_age',
        'female_age'     => 'female_age',
        'male_sleep'     => 'male_sleep',
        'female_sleep'   => 'female_sleep',
        'male_stress'    => 'male_stress',
        'female_stress'  => 'female_stress',
        'male_happy'     => 'male_happy',
        'female_happy'   => 'female_happy',
    ];
}

function validOrderColsAgg(): array {
    return [
        'platform'          => 'platform',
        'avg_male_sleep'    => 'avg_male_sleep',
        'avg_male_stress'   => 'avg_male_stress',
        'avg_male_happy'    => 'avg_male_happy',
        'count_male'        => 'count_male',
        'avg_female_sleep'  => 'avg_female_sleep',
        'avg_female_stress' => 'avg_female_stress',
        'avg_female_happy'  => 'avg_female_happy',
        'count_female'      => 'count_female',
    ];
}

function fetchPairs(mysqli $conn, ?string $platform, int $minAge, int $maxAge, string $orderCol, string $dir, int $limit): array {
    $sql = "
      SELECT
        m.`Social_Media_Platform`            AS platform,
        m.`User_ID`                          AS male_id,
        m.`Age`                              AS male_age,
        m.`Sleep_Quality(1-10)`              AS male_sleep,
        m.`Stress_Level(1-10)`               AS male_stress,
        m.`Happiness_Index(1-10)`            AS male_happy,
        f.`User_ID`                          AS female_id,
        f.`Age`                              AS female_age,
        f.`Sleep_Quality(1-10)`              AS female_sleep,
        f.`Stress_Level(1-10)`               AS female_stress,
        f.`Happiness_Index(1-10)`            AS female_happy
      FROM `mental_health_male` AS m
      JOIN `mental_health_female` AS f
        ON m.`Social_Media_Platform` = f.`Social_Media_Platform`
      WHERE 1=1
    ";

    $types = '';
    $params = [];

    if ($platform !== null && $platform !== '') {
        $sql .= " AND m.`Social_Media_Platform` = ?";
        $types .= 's';
        $params[] = $platform;
    }
    if ($minAge > 0) {
        $sql .= " AND m.`Age` >= ? AND f.`Age` >= ?";
        $types .= 'ii';
        $params[] = $minAge;
        $params[] = $minAge;
    }
    if ($maxAge > 0) {
        $sql .= " AND m.`Age` <= ? AND f.`Age` <= ?";
        $types .= 'ii';
        $params[] = $maxAge;
        $params[] = $maxAge;
    }

    $dirSql = ($dir === 'DESC') ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $orderCol $dirSql LIMIT ?";
    $types .= 'i';
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function fetchAgg(mysqli $conn, ?string $platform, int $minAge, int $maxAge, string $orderCol, string $dir, int $limit): array {
    $sql = "
      SELECT
        p.platform_name                        AS platform,
        ROUND(AVG(m.`Sleep_Quality(1-10)`),2)  AS avg_male_sleep,
        ROUND(AVG(m.`Stress_Level(1-10)`),2)   AS avg_male_stress,
        ROUND(AVG(m.`Happiness_Index(1-10)`),2)AS avg_male_happy,
        COUNT(m.`User_ID`)                     AS count_male,
        ROUND(AVG(f.`Sleep_Quality(1-10)`),2)  AS avg_female_sleep,
        ROUND(AVG(f.`Stress_Level(1-10)`),2)   AS avg_female_stress,
        ROUND(AVG(f.`Happiness_Index(1-10)`),2)AS avg_female_happy,
        COUNT(f.`User_ID`)                     AS count_female
      FROM (
        SELECT DISTINCT `Social_Media_Platform` AS platform_name FROM `mental_health_male`
        UNION
        SELECT DISTINCT `Social_Media_Platform` AS platform_name FROM `mental_health_female`
      ) AS p
      LEFT JOIN `mental_health_male` AS m
        ON p.platform_name = m.`Social_Media_Platform`
      LEFT JOIN `mental_health_female` AS f
        ON p.platform_name = f.`Social_Media_Platform`
      WHERE 1=1
    ";

    $types = '';
    $params = [];

    if ($platform !== null && $platform !== '') {
        $sql .= " AND p.platform_name = ?";
        $types .= 's';
        $params[] = $platform;
    }
    if ($minAge > 0) {
        $sql .= " AND ( (m.`Age` IS NULL OR m.`Age` >= ?) AND (f.`Age` IS NULL OR f.`Age` >= ?) )";
        $types .= 'ii';
        $params[] = $minAge;
        $params[] = $minAge;
    }
    if ($maxAge > 0) {
        $sql .= " AND ( (m.`Age` IS NULL OR m.`Age` <= ?) AND (f.`Age` IS NULL OR f.`Age` <= ?) )";
        $types .= 'ii';
        $params[] = $maxAge;
        $params[] = $maxAge;
    }

    $sql .= " GROUP BY p.platform_name";

    $dirSql = ($dir === 'DESC') ? 'DESC' : 'ASC';
    $sql .= " ORDER BY $orderCol $dirSql LIMIT ?";
    $types .= 'i';
    $params[] = $limit;

    $stmt = $conn->prepare($sql);
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}


function percentBar($value, $max = 10) {
    $v = max(0, min((float)$value, (float)$max));
    return $max > 0 ? (int)(($v / $max) * 100) : 0;
}

function countPairs(mysqli $conn, ?string $platform, int $minAge, int $maxAge): int {
    $sql = "
      SELECT COUNT(*) AS c
      FROM `mental_health_male` m
      JOIN `mental_health_female` f
        ON m.`Social_Media_Platform` = f.`Social_Media_Platform`
      WHERE 1=1
    ";
    $types = ''; $params = [];
    if ($platform) { $sql .= " AND m.`Social_Media_Platform` = ?"; $types.='s'; $params[]=$platform; }
    if ($minAge > 0) { $sql .= " AND m.`Age` >= ? AND f.`Age` >= ?"; $types.='ii'; $params[]=$minAge; $params[]=$minAge; }
    if ($maxAge > 0) { $sql .= " AND m.`Age` <= ? AND f.`Age` <= ?"; $types.='ii'; $params[]=$maxAge; $params[]=$maxAge; }
    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $c = (int)($st->get_result()->fetch_assoc()['c'] ?? 0);
    $st->close();
    return $c;
}

function countAgg(mysqli $conn, ?string $platform, int $minAge, int $maxAge): int {
    $sql = "
      SELECT p.platform_name
      FROM (
        SELECT DISTINCT `Social_Media_Platform` AS platform_name FROM `mental_health_male`
        UNION
        SELECT DISTINCT `Social_Media_Platform` FROM `mental_health_female`
      ) p
      LEFT JOIN `mental_health_male` m ON p.platform_name = m.`Social_Media_Platform`
      LEFT JOIN `mental_health_female` f ON p.platform_name = f.`Social_Media_Platform`
      WHERE 1=1
    ";
    $types=''; $params=[];
    if ($platform) { $sql .= " AND p.platform_name = ?"; $types.='s'; $params[]=$platform; }
    if ($minAge > 0) { $sql .= " AND ((m.`Age` IS NULL OR m.`Age` >= ?) AND (f.`Age` IS NULL OR f.`Age` >= ?))"; $types.='ii'; $params[]=$minAge; $params[]=$minAge; }
    if ($maxAge > 0) { $sql .= " AND ((m.`Age` IS NULL OR m.`Age` <= ?) AND (f.`Age` IS NULL OR f.`Age` <= ?))"; $types.='ii'; $params[]=$maxAge; $params[]=$maxAge; }
    $sql .= " GROUP BY p.platform_name";

    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    $count = $res->num_rows; 
    $st->close();
    return (int)$count;
}


function fetchPairsPaged(mysqli $conn, ?string $platform, int $minAge, int $maxAge, string $orderCol, string $dir, int $limit, int $offset): array {
    $sql = "
      SELECT
        m.`Social_Media_Platform` AS platform,
        m.`User_ID` AS male_id, m.`Age` AS male_age,
        m.`Sleep_Quality(1-10)` AS male_sleep,
        m.`Stress_Level(1-10)` AS male_stress,
        m.`Happiness_Index(1-10)` AS male_happy,
        f.`User_ID` AS female_id, f.`Age` AS female_age,
        f.`Sleep_Quality(1-10)` AS female_sleep,
        f.`Stress_Level(1-10)` AS female_stress,
        f.`Happiness_Index(1-10)` AS female_happy
      FROM `mental_health_male` m
      JOIN `mental_health_female` f
        ON m.`Social_Media_Platform` = f.`Social_Media_Platform`
      WHERE 1=1
    ";
    $types=''; $params=[];
    if ($platform) { $sql .= " AND m.`Social_Media_Platform` = ?"; $types.='s'; $params[]=$platform; }
    if ($minAge > 0) { $sql .= " AND m.`Age` >= ? AND f.`Age` >= ?"; $types.='ii'; $params[]=$minAge; $params[]=$minAge; }
    if ($maxAge > 0) { $sql .= " AND m.`Age` <= ? AND f.`Age` <= ?"; $types.='ii'; $params[]=$maxAge; $params[]=$maxAge; }
    $sql .= " ORDER BY $orderCol " . ($dir==='DESC'?'DESC':'ASC') . " LIMIT ? OFFSET ?";
    $types.='ii'; $params[]=$limit; $params[]=$offset;

    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function fetchAggPaged(mysqli $conn, ?string $platform, int $minAge, int $maxAge, string $orderCol, string $dir, int $limit, int $offset): array {
    $sql = "
      SELECT
        p.platform_name AS platform,
        ROUND(AVG(m.`Sleep_Quality(1-10)`),2)  AS avg_male_sleep,
        ROUND(AVG(m.`Stress_Level(1-10)`),2)   AS avg_male_stress,
        ROUND(AVG(m.`Happiness_Index(1-10)`),2)AS avg_male_happy,
        COUNT(m.`User_ID`)                     AS count_male,
        ROUND(AVG(f.`Sleep_Quality(1-10)`),2)  AS avg_female_sleep,
        ROUND(AVG(f.`Stress_Level(1-10)`),2)   AS avg_female_stress,
        ROUND(AVG(f.`Happiness_Index(1-10)`),2)AS avg_female_happy,
        COUNT(f.`User_ID`)                     AS count_female
      FROM (
        SELECT DISTINCT `Social_Media_Platform` AS platform_name FROM `mental_health_male`
        UNION
        SELECT DISTINCT `Social_Media_Platform` FROM `mental_health_female`
      ) p
      LEFT JOIN `mental_health_male` m ON p.platform_name = m.`Social_Media_Platform`
      LEFT JOIN `mental_health_female` f ON p.platform_name = f.`Social_Media_Platform`
      WHERE 1=1
    ";
    $types=''; $params=[];
    if ($platform) { $sql .= " AND p.platform_name = ?"; $types.='s'; $params[]=$platform; }
    if ($minAge > 0) { $sql .= " AND ((m.`Age` IS NULL OR m.`Age` >= ?) AND (f.`Age` IS NULL OR f.`Age` >= ?))"; $types.='ii'; $params[]=$minAge; $params[]=$minAge; }
    if ($maxAge > 0) { $sql .= " AND ((m.`Age` IS NULL OR m.`Age` <= ?) AND (f.`Age` IS NULL OR f.`Age` <= ?))"; $types.='ii'; $params[]=$maxAge; $params[]=$maxAge; }
    $sql .= " GROUP BY p.platform_name ORDER BY $orderCol " . ($dir==='DESC'?'DESC':'ASC') . " LIMIT ? OFFSET ?";
    $types.='ii'; $params[]=$limit; $params[]=$offset;

    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

