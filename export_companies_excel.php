<?php
// export_companies_excel.php - Fixed Excel Export with Correct Database Schema
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="companies_export_' . date('Y-m-d_His') . '.xls"');
header('Pragma: no-cache');
header('Expires: 0');

// Database connection
$conn = new mysqli("localhost", "root", "", "nexttern_db");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$industry = $_GET['industry'] ?? 'all';
$report_type = $_GET['report_type'] ?? 'current';

// Build query based on filters
$where_conditions = array();
$params = array();
$types = '';

if ($status !== 'all') {
    $where_conditions[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(company_name LIKE ? OR company_email LIKE ? OR company_id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

if ($industry !== 'all') {
    $where_conditions[] = "industry_type = ?";
    $params[] = $industry;
    $types .= 's';
}

// Special filters for report types
if ($report_type === 'monthly') {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    $where_conditions[] = "MONTH(created_at) = ? AND YEAR(created_at) = ?";
    $params[] = $month;
    $params[] = $year;
    $types .= 'ii';
} elseif ($report_type === 'quarterly') {
    $quarter = $_GET['quarter'] ?? ceil(date('m') / 3);
    $year = $_GET['year'] ?? date('Y');
    $start_month = ($quarter - 1) * 3 + 1;
    $end_month = $quarter * 3;
    $where_conditions[] = "YEAR(created_at) = ? AND MONTH(created_at) BETWEEN ? AND ?";
    $params[] = $year;
    $params[] = $start_month;
    $params[] = $end_month;
    $types .= 'iii';
}

$where_clause = count($where_conditions) > 0 ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Corrected query based on actual database schema
$query = "SELECT id, company_id, company_name, industry_type, company_email, 
          year_established, contact_name, designation, contact_phone, status, 
          login_count, first_login, last_login,
          DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as registration_date,
          DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as last_updated
          FROM companies 
          $where_clause 
          ORDER BY created_at DESC";

if (!empty($params)) {
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        die("Query preparation failed: " . $conn->error);
    }
} else {
    $result = $conn->query($query);
    if (!$result) {
        die("Query execution failed: " . $conn->error);
    }
}

// Get statistics for all companies
$total_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
    FROM companies";
$stats_result = $conn->query($total_query);
$stats = $stats_result->fetch_assoc();

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
        }
        .header { 
            background-color: #035946; 
            color: white; 
            padding: 20px; 
            margin-bottom: 30px; 
            border-radius: 8px;
        }
        .header h1 { 
            margin: 0 0 10px 0; 
            font-size: 28px; 
            font-weight: bold;
        }
        .header p { 
            margin: 5px 0; 
            font-size: 14px; 
            opacity: 0.9;
        }
        .filter-info {
            background-color: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 6px;
            margin-top: 15px;
        }
        .filter-info h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
        }
        .filter-item {
            display: inline-block;
            margin-right: 20px;
            font-size: 13px;
        }
        .stats { 
            margin-bottom: 30px; 
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .stats h2 {
            margin: 0 0 15px 0;
            color: #035946;
            font-size: 18px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #035946;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-label { 
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stat-value { 
            font-size: 24px;
            font-weight: bold;
            color: #035946;
        }
        .stat-card.active .stat-value { color: #27ae60; }
        .stat-card.pending .stat-value { color: #9b59b6; }
        .stat-card.inactive .stat-value { color: #e74c3c; }
        
        table { 
            border-collapse: collapse; 
            width: 100%; 
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        th { 
            background-color: #035946; 
            color: white; 
            padding: 15px 12px; 
            text-align: left; 
            border: none;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td { 
            padding: 12px; 
            border-bottom: 1px solid #eee; 
            font-size: 13px;
            vertical-align: top;
        }
        tr:nth-child(even) { 
            background-color: #f8fcfb; 
        }
        tr:hover {
            background-color: #e8f5f0;
        }
        .company-id {
            font-family: 'Courier New', monospace;
            background-color: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .company-name {
            font-weight: bold;
            color: #035946;
            font-size: 14px;
        }
        .status-active { 
            color: #27ae60; 
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pending { 
            color: #9b59b6; 
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-inactive { 
            color: #e74c3c; 
            font-weight: bold;
            text-transform: uppercase;
        }
        .industry-badge {
            background-color: #e8f5f0;
            color: #035946;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .contact-info {
            line-height: 1.4;
        }
        .contact-name {
            font-weight: bold;
            color: #333;
        }
        .contact-detail {
            font-size: 12px;
            color: #666;
        }
        .login-info {
            font-size: 12px;
            line-height: 1.3;
        }
        .login-count {
            font-weight: bold;
            color: #035946;
        }
        .footer { 
            margin-top: 40px; 
            padding: 20px; 
            background-color: #f8f9fa; 
            font-size: 11px; 
            color: #666;
            border-radius: 8px;
            border-top: 3px solid #035946;
        }
        .footer p {
            margin: 5px 0;
        }
        .footer strong {
            color: #035946;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        @media print {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Company Export Report</h1>
        <p>Generated on: <?= date('F j, Y g:i A') ?></p>
        <p>Report Type: <?= ucfirst(str_replace('_', ' ', $report_type)) ?></p>
        
        <?php if (!empty($search) || $status !== 'all' || !empty($date_from) || !empty($date_to) || $industry !== 'all'): ?>
            <div class="filter-info">
                <h3>Applied Filters:</h3>
                <?php if (!empty($search)): ?>
                    <span class="filter-item"><strong>Search:</strong> <?= htmlspecialchars($search) ?></span>
                <?php endif; ?>
                <?php if ($status !== 'all'): ?>
                    <span class="filter-item"><strong>Status:</strong> <?= ucfirst($status) ?></span>
                <?php endif; ?>
                <?php if ($industry !== 'all'): ?>
                    <span class="filter-item"><strong>Industry:</strong> <?= htmlspecialchars($industry) ?></span>
                <?php endif; ?>
                <?php if (!empty($date_from)): ?>
                    <span class="filter-item"><strong>From:</strong> <?= htmlspecialchars($date_from) ?></span>
                <?php endif; ?>
                <?php if (!empty($date_to)): ?>
                    <span class="filter-item"><strong>To:</strong> <?= htmlspecialchars($date_to) ?></span>
                <?php endif; ?>
                <?php if ($report_type === 'monthly'): ?>
                    <span class="filter-item"><strong>Period:</strong> <?= date('F Y', mktime(0, 0, 0, $_GET['month'] ?? date('m'), 1, $_GET['year'] ?? date('Y'))) ?></span>
                <?php elseif ($report_type === 'quarterly'): ?>
                    <span class="filter-item"><strong>Period:</strong> Q<?= $_GET['quarter'] ?? ceil(date('m') / 3) ?> <?= $_GET['year'] ?? date('Y') ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="stats">
        <h2>Database Statistics</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Companies</div>
                <div class="stat-value"><?= $stats['total'] ?></div>
            </div>
            <div class="stat-card active">
                <div class="stat-label">Active</div>
                <div class="stat-value"><?= $stats['active'] ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?= $stats['pending'] ?></div>
            </div>
            <div class="stat-card inactive">
                <div class="stat-label">Inactive</div>
                <div class="stat-value"><?= $stats['inactive'] ?></div>
            </div>
        </div>
        <p style="margin-top: 15px; font-size: 12px; color: #666;">
            <strong>Exported Records:</strong> <?= $result->num_rows ?> companies match the selected criteria
        </p>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Company ID</th>
                    <th>Company Name</th>
                    <th>Industry</th>
                    <th>Email</th>
                    <th>Year Est.</th>
                    <th>Contact Person</th>
                    <th>Designation</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Login Activity</th>
                    <th>Registration Date</th>
                    <th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td>
                            <span class="company-id"><?= htmlspecialchars($row['company_id']) ?></span>
                        </td>
                        <td>
                            <div class="company-name"><?= htmlspecialchars($row['company_name']) ?></div>
                        </td>
                        <td>
                            <span class="industry-badge"><?= htmlspecialchars($row['industry_type']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($row['company_email']) ?></td>
                        <td><?= htmlspecialchars($row['year_established']) ?></td>
                        <td>
                            <div class="contact-info">
                                <div class="contact-name"><?= htmlspecialchars($row['contact_name']) ?></div>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($row['designation']) ?></td>
                        <td><?= htmlspecialchars($row['contact_phone']) ?></td>
                        <td>
                            <span class="status-<?= $row['status'] ?>">
                                <?= ucfirst($row['status']) ?>
                            </span>
                        </td>
                        <td>
                            <div class="login-info">
                                <div class="login-count">Logins: <?= $row['login_count'] ?? 0 ?></div>
                                <div class="contact-detail">
                                    First: <?= $row['first_login'] ? date('M j, Y', strtotime($row['first_login'])) : 'Never' ?>
                                </div>
                                <div class="contact-detail">
                                    Last: <?= $row['last_login'] ? date('M j, Y', strtotime($row['last_login'])) : 'Never' ?>
                                </div>
                            </div>
                        </td>
                        <td><?= $row['registration_date'] ?></td>
                        <td><?= $row['last_updated'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">
            <h3>No Companies Found</h3>
            <p>No companies match the selected criteria. Please adjust your filters and try again.</p>
        </div>
    <?php endif; ?>

    <div class="footer">
        <p><strong>Report Details:</strong></p>
        <p>• This export contains company data as of <?= date('F j, Y g:i A') ?></p>
        <p>• Data may have changed since this report was generated</p>
        <p>• Total records exported: <?= $result ? $result->num_rows : 0 ?></p>
        <p>• Generated by: Nexttern Company Management System</p>
        <?php if ($result && $result->num_rows > 0): ?>
            <p>• Export includes: Company details, contact information, login activity, and registration data</p>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>