<?php
// populate_internship_subtypes.php
// Heuristic script to populate missing project_subtype and project_type values
require_once __DIR__ . '/../db.php';

$rows = [];
$res = mysqli_query($conn, "SELECT id, title, technology_stack, description, project_type, project_subtype, difficulty_level FROM internships WHERE project_subtype IS NULL OR TRIM(project_subtype) = '' OR project_type IS NULL OR TRIM(project_type) = ''");
if (!$res) {
    echo "ERROR: " . mysqli_error($conn) . "\n";
    exit(1);
}

$updated = 0;
while ($r = mysqli_fetch_assoc($res)) {
    $id = intval($r['id']);
    $title = strtolower($r['title'] ?? '');
    $tech = strtolower($r['technology_stack'] ?? '');
    $desc = strtolower($r['description'] ?? '');

    $combined = $title . ' ' . $tech . ' ' . $desc;

    $subtype = null;
    if (preg_match('/\b(frontend|react|angular|vue|web|javascript|html|css|frontend)\b/i', $combined)) {
        $subtype = 'Web Development';
    } elseif (preg_match('/\b(data|python|machine learning|ml|data science|analytics|sql)\b/i', $combined)) {
        $subtype = 'Data Science';
    } elseif (preg_match('/\b(ui|ux|design|figma|adobe|photoshop|illustrator)\b/i', $combined)) {
        $subtype = 'UI/UX Design';
    } elseif (preg_match('/\b(node|php|java|backend|api|database|sql|nosql)\b/i', $combined)) {
        $subtype = 'Backend Development';
    } else {
        $subtype = 'General';
    }

    $ptype = $r['project_type'];
    if (empty(trim((string)$ptype))) $ptype = 'Development';

    $difficulty = $r['difficulty_level'];
    if (empty(trim((string)$difficulty))) $difficulty = 'Medium';

    $stmt = mysqli_prepare($conn, "UPDATE internships SET project_subtype = ?, project_type = ?, difficulty_level = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'sssi', $subtype, $ptype, $difficulty, $id);
    if (mysqli_stmt_execute($stmt)) {
        $updated++;
        echo "Updated internship id={$id} -> project_type={$ptype}, project_subtype={$subtype}, difficulty_level={$difficulty}\n";
    } else {
        echo "Failed update id={$id}: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($stmt);
}

echo "Done. Updated {$updated} rows.\n";
