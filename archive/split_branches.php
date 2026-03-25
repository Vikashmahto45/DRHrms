<?php
$f_main = 'superadmin/main_branches.php';
$c_main = file_get_contents($f_main);

// 1. Replace UI Titles
$c_main = str_replace('Manage Companies - DRHrms', 'Main Branches - DRHrms', $c_main);
$c_main = str_replace('<h1>Manage Companies</h1>', '<h1>Main Branches</h1>', $c_main);
$c_main = str_replace('<p style="color:var(--text-muted)">Create, suspend or delete tenant companies.</p>', '<p style="color:var(--text-muted)">Create and manage independent main branch/franchise agencies.</p>', $c_main);
$c_main = str_replace('<h2>All Companies', '<h2>All Main Branches', $c_main);
$c_main = str_replace('<h3>Create New Company</h3>', '<h3>Create New Main Branch</h3>', $c_main);

// 2. Redirects
$c_main = str_replace('header("Location: companies.php");', 'header("Location: main_branches.php");', $c_main);

// 3. Database Filters
$c_main = str_replace('GROUP BY c.id', 'WHERE c.is_main_branch = 1 GROUP BY c.id', $c_main);

// 4. Force Branch Type POST
$c_main = preg_replace('/\$is_main_branch = .*?;/', '$is_main_branch = 1;', $c_main);
$c_main = preg_replace('/\$parent_id = .*?;/', '$parent_id = null;', $c_main);

// 5. Hide Modal Logic for Branch Selection
$c_main = preg_replace('/<div class="form-group">\s*<label>Branch Type<\/label>.*?<\/select>\s*<\/div>/s', '<input type="hidden" name="is_main_branch" value="1">', $c_main);

file_put_contents($f_main, $c_main);

// ----------------

$f_sub = 'superadmin/sub_branches.php';
$c_sub = file_get_contents($f_sub);

// 1. Replace UI Titles
$c_sub = str_replace('Manage Companies - DRHrms', 'Sub-Branches - DRHrms', $c_sub);
$c_sub = str_replace('<h1>Manage Companies</h1>', '<h1>Sub-Branches (Sales Groups)</h1>', $c_sub);
$c_sub = str_replace('<p style="color:var(--text-muted)">Create, suspend or delete tenant companies.</p>', '<p style="color:var(--text-muted)">Create and assign sub-branches under a parent agency.</p>', $c_sub);
$c_sub = str_replace('<h2>All Companies', '<h2>All Sub-Branches', $c_sub);
$c_sub = str_replace('<h3>Create New Company</h3>', '<h3>Create New Sub-Branch</h3>', $c_sub);

// 2. Redirects
$c_sub = str_replace('header("Location: companies.php");', 'header("Location: sub_branches.php");', $c_sub);

// 3. Database Filters
$c_sub = str_replace('GROUP BY c.id', 'WHERE c.is_main_branch = 0 GROUP BY c.id', $c_sub);

// 4. Force Branch Type POST
$c_sub = preg_replace('/\$is_main_branch = .*?;/', '$is_main_branch = 0;', $c_sub);

// 5. Hide Modal Logic for Branch Selection
$c_sub = preg_replace('/<div class="form-group">\s*<label>Branch Type<\/label>.*?<\/select>\s*<\/div>/s', '<input type="hidden" name="is_main_branch" value="0">', $c_sub);

// Show parent by default
$c_sub = str_replace('id="parentBranchGroup" style="display:none;"', 'id="parentBranchGroup"', $c_sub);
$c_sub = str_replace('id="commissionGroup" style="display:none;"', 'id="commissionGroup"', $c_sub);

file_put_contents($f_sub, $c_sub);

// Rename original companies.php so it's not accessed
rename('superadmin/companies.php', 'superadmin/companies_deprecated.php');
echo "Done replacing contents.";
?>
