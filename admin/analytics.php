<?php ob_start();
// admin/analytics.php
session_start();
require_once '../includes/db.php';
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireRole('admin', '/login.php');

// ─── Field definitions ────────────────────────────────────────────────────────
$all_fields = [
    // Core booking
    'pnr'                   => 'PNR',
    'passenger_name'        => 'Passenger Name',
    'email'                 => 'Email',
    'phone'                 => 'Phone',
    'emergency_contact'     => 'Emergency Contact',
    'special_requests'      => 'Special Requests',
    // Trip
    'trip_date'             => 'Trip Date',
    'seat_number'           => 'Seat No.',
    'pickup_city'           => 'Pickup City',
    'dropoff_city'          => 'Dropoff City',
    'departure_time'        => 'Departure',
    'arrival_time'          => 'Arrival',
    'vehicle_type'          => 'Vehicle Type',
    'vehicle_number'        => 'Vehicle No.',
    'driver_name'           => 'Driver',
    // Financial
    'total_amount'          => 'Amount (N)',
    'payment_status'        => 'Payment Status',
    'payment_method'        => 'Payment Method',
    'transaction_reference' => 'Transaction Ref',
    'gateway_reference'     => 'Gateway Ref',
    'payment_date'          => 'Payment Date',
    // Booking meta
    'status'                => 'Booking Status',
    'free_booking_used'     => 'Free Booking',
    'created_at'            => 'Booked At',
    // Account
    'user_email'            => 'Account Email',
];

$default_export_cols  = ['pnr','passenger_name','phone','email','trip_date','pickup_city','dropoff_city','departure_time','seat_number','total_amount','payment_status','payment_method','status'];
$optional_export_cols = ['emergency_contact','special_requests','arrival_time','vehicle_type','vehicle_number','driver_name','transaction_reference','gateway_reference','payment_date','free_booking_used','created_at','user_email'];

// ─── Filters ──────────────────────────────────────────────────────────────────
$date_from      = trim($_GET['date_from']      ?? '');
$date_to        = trim($_GET['date_to']        ?? '');
$booking_status = trim($_GET['booking_status'] ?? '');
$payment_status = trim($_GET['payment_status'] ?? '');
$payment_method = trim($_GET['payment_method'] ?? '');
$pickup_city    = trim($_GET['pickup_city']    ?? '');
$dropoff_city   = trim($_GET['dropoff_city']   ?? '');
$search         = trim($_GET['search']         ?? '');
$page           = max(1, (int)($_GET['page']   ?? 1));
$per_page       = 25;

// Display columns for table
$display_default = ['pnr','passenger_name','phone','trip_date','pickup_city','dropoff_city','departure_time','total_amount','payment_method','payment_status','status'];
$selected_cols   = isset($_GET['cols']) && is_array($_GET['cols'])
                     ? array_values(array_intersect(array_keys($all_fields), $_GET['cols']))
                     : $display_default;
if (empty($selected_cols)) $selected_cols = $display_default;

// ─── Query builder ────────────────────────────────────────────────────────────
function buildQuery(mysqli $conn, array $filters, bool $count_only = false): array {
    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if (!empty($filters['date_from']))      { $where[] = 'b.trip_date >= ?';          $params[] = $filters['date_from'];      $types .= 's'; }
    if (!empty($filters['date_to']))        { $where[] = 'b.trip_date <= ?';          $params[] = $filters['date_to'];        $types .= 's'; }
    if (!empty($filters['booking_status'])) { $where[] = 'b.status = ?';              $params[] = $filters['booking_status']; $types .= 's'; }
    if (!empty($filters['payment_status'])) { $where[] = 'b.payment_status = ?';      $params[] = $filters['payment_status']; $types .= 's'; }
    if (!empty($filters['pickup_city']))    { $where[] = 'pc.id = ?';                 $params[] = (int)$filters['pickup_city']; $types .= 'i'; }
    if (!empty($filters['dropoff_city']))   { $where[] = 'dc.id = ?';                 $params[] = (int)$filters['dropoff_city']; $types .= 'i'; }
    if (!empty($filters['payment_method'])) { $where[] = 'p.payment_method = ?';      $params[] = $filters['payment_method']; $types .= 's'; }
    if (!empty($filters['search'])) {
        $like    = '%' . $filters['search'] . '%';
        $where[] = '(b.pnr LIKE ? OR b.passenger_name LIKE ? OR b.email LIKE ? OR b.phone LIKE ? OR p.transaction_reference LIKE ?)';
        $params  = array_merge($params, [$like,$like,$like,$like,$like]);
        $types  .= 'sssss';
    }

    $w = implode(' AND ', $where);

    $joins = "FROM bookings b
              JOIN trip_templates tt  ON b.template_id     = tt.id
              JOIN cities pc          ON tt.pickup_city_id  = pc.id
              JOIN cities dc          ON tt.dropoff_city_id = dc.id
              JOIN time_slots ts      ON tt.time_slot_id    = ts.id
              JOIN vehicles v         ON tt.vehicle_id      = v.id
              JOIN vehicle_types vt   ON tt.vehicle_type_id = vt.id
              LEFT JOIN payments p    ON p.booking_id       = b.id AND p.status = 'completed'
              LEFT JOIN users u       ON b.user_id          = u.id
              WHERE $w";

    $sql = $count_only
        ? "SELECT COUNT(*) AS total $joins"
        : "SELECT
                b.id,
                b.pnr,
                b.passenger_name,
                b.email,
                b.phone,
                b.emergency_contact,
                b.special_requests,
                b.trip_date,
                b.seat_number,
                pc.name                             AS pickup_city,
                dc.name                             AS dropoff_city,
                ts.departure_time,
                ts.arrival_time,
                vt.type                             AS vehicle_type,
                v.vehicle_number,
                v.driver_name,
                b.total_amount,
                b.payment_status,
                b.status,
                b.created_at,
                b.free_booking_used,
                COALESCE(p.payment_method, '')      AS payment_method,
                COALESCE(p.transaction_reference,'') AS transaction_reference,
                COALESCE(p.gateway_reference,'')    AS gateway_reference,
                p.created_at                        AS payment_date,
                u.email                             AS user_email
           $joins
           ORDER BY b.trip_date DESC, b.created_at DESC";

    return ['sql' => $sql, 'params' => $params, 'types' => $types];
}

function runQuery(mysqli $conn, array $q): mysqli_result {
    $stmt = $conn->prepare($q['sql']);
    if (!$stmt) die("Query prepare failed: " . $conn->error);
    if (!empty($q['params'])) $stmt->bind_param($q['types'], ...$q['params']);
    $stmt->execute();
    return $stmt->get_result();
}

$filters = compact('date_from','date_to','booking_status','payment_status','payment_method','pickup_city','dropoff_city','search');

// ─── CSV EXPORT ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_cols = [];
    $raw = $_GET['export_cols'] ?? [];
    if (is_array($raw)) {
        $export_cols = array_values(array_intersect(array_keys($all_fields), $raw));
    }
    if (empty($export_cols)) $export_cols = $default_export_cols;

    $result = runQuery($conn, buildQuery($conn, $filters, false));

    while (ob_get_level() > 0) ob_end_clean();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="bookings_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map(fn($k) => $all_fields[$k], $export_cols));

    while ($row = $result->fetch_assoc()) {
        $line = [];
        foreach ($export_cols as $col) {
            $val = $row[$col] ?? '';
            if ($col === 'total_amount')      $val = number_format((float)$val, 2, '.', '');
            if ($col === 'free_booking_used') $val = $val ? 'Yes' : 'No';
            if ($col === 'payment_method')    $val = formatPaymentMethod((string)$val);
            $line[] = $val;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

// ─── Count + paginate ─────────────────────────────────────────────────────────
$total_rows  = (int)runQuery($conn, buildQuery($conn, $filters, true))->fetch_assoc()['total'];
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$data_q         = buildQuery($conn, $filters, false);
$data_q['sql'] .= " LIMIT $per_page OFFSET $offset";
$bookings       = runQuery($conn, $data_q)->fetch_all(MYSQLI_ASSOC);

// ─── Summary stats ────────────────────────────────────────────────────────────
$sq = buildQuery($conn, $filters, true);
$sq['sql'] = str_replace('SELECT COUNT(*) AS total',
    "SELECT
        COUNT(*)                                                                   AS total_bookings,
        SUM(CASE WHEN b.status='confirmed'        THEN 1 ELSE 0 END)              AS confirmed,
        SUM(CASE WHEN b.status='cancelled'        THEN 1 ELSE 0 END)              AS cancelled,
        SUM(CASE WHEN b.status='pending'          THEN 1 ELSE 0 END)              AS pending,
        SUM(CASE WHEN b.payment_status='paid'     THEN b.total_amount ELSE 0 END) AS total_revenue,
        SUM(CASE WHEN b.free_booking_used=1       THEN 1 ELSE 0 END)              AS free_count",
    $sq['sql']);
$stats = runQuery($conn, $sq)->fetch_assoc();



// ─── Distinct payment methods from DB ────────────────────────────────────────
$pm_result = $conn->query("SELECT DISTINCT payment_method FROM payments WHERE payment_method IS NOT NULL AND payment_method != '' ORDER BY payment_method");
$payment_methods_list = [];
while ($pm_row = $pm_result->fetch_assoc()) {
    $raw = $pm_row['payment_method'];
    $payment_methods_list[] = ['raw' => $raw, 'label' => formatPaymentMethod($raw)];
}

// ─── Cities ───────────────────────────────────────────────────────────────────
$cities = $conn->query("SELECT id, name FROM cities ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatPaymentMethod(string $m): string {
    $map = [
        'paystack'      => 'Paystack',
        'manual'        => 'Manual',
        'cash'          => 'Cash',
        'transfer'      => 'Bank Transfer',
        'bank_transfer' => 'Bank Transfer',
        'bank transfer' => 'Bank Transfer',
        'credit'        => 'Credits',
        'credits'       => 'Credits',
        'free'          => 'Free Booking',
        ''              => '—',
    ];
    return $map[strtolower(trim($m))] ?? ucfirst($m);
}

function methodBadge(string $m): string {
    return formatPaymentMethod($m)
        ? '<span class="text-sm text-gray-800">' . htmlspecialchars(formatPaymentMethod($m)) . '</span>'
        : '<span class="text-gray-400">—</span>';
}

function statusBadge(string $v): string {
    $map = ['confirmed'=>'bg-green-100 text-green-800','paid'=>'bg-green-100 text-green-800',
            'pending'=>'bg-yellow-100 text-yellow-800','cancelled'=>'bg-red-100 text-red-800',
            'failed'=>'bg-red-100 text-red-800'];
    $cls = $map[strtolower($v)] ?? 'bg-gray-100 text-gray-800';
    return "<span class=\"inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium $cls\">" . htmlspecialchars(ucfirst($v)) . "</span>";
}

function qstr(array $ov = []): string {
    $p = array_merge($_GET, $ov);
    unset($p['export'],$p['export_cols']);
    return '?' . http_build_query($p);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Analytics - DGC Transports</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{colors:{'primary-red':'#990b0a','dark-red':'#7a0908'}}}}</script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body{font-family:'Inter',sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%)}
        .form-input{border:1px solid #d1d5db;border-radius:6px;padding:7px 11px;font-size:.8125rem;width:100%;transition:all .2s;background:white}
        .form-input:focus{outline:none;border-color:#990b0a;box-shadow:0 0 0 2px rgba(153,11,10,.15)}
        .card{background:white;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06)}
        .th{padding:10px 14px;text-align:left;font-size:.7rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;background:#f9fafb;white-space:nowrap;border-bottom:1px solid #e5e7eb}
        .td{padding:10px 14px;font-size:.8125rem;color:#111827;white-space:nowrap;border-top:1px solid #f3f4f6}
        .tr:hover .td{background:#fafafa}
        .stat-card{background:white;border-radius:12px;padding:18px 20px;box-shadow:0 1px 3px rgba(0,0,0,.08);border-left:4px solid}
        .col-checkbox{accent-color:#990b0a;width:14px;height:14px;cursor:pointer}
        #col-picker{display:none} #col-picker.open{display:block}
        #export-modal{display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center}
        #export-modal.open{display:flex}
        .modal-overlay{position:absolute;inset:0;background:rgba(0,0,0,.55)}
        .modal-box{position:relative;background:white;border-radius:16px;width:100%;max-width:560px;max-height:92vh;overflow-y:auto;box-shadow:0 25px 50px rgba(0,0,0,.3);margin:16px}
        .export-cb{width:14px;height:14px;cursor:pointer}
        .section-block{border-radius:10px;padding:14px;border:1px solid}
    </style>
</head>
<body class="min-h-screen">
<?php include '../templates/sidebar.php'; ?>

<div class="md:ml-64 min-h-screen pt-16 md:pt-0">
<div class="p-4 md:p-6 lg:p-8">
<div class="max-w-screen-2xl mx-auto">

    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">
                <i class="fas fa-chart-bar text-primary-red mr-3"></i>Booking Analytics
            </h1>
            <p class="text-sm text-gray-500 mt-1">Filter, review, and export all booking data</p>
        </div>
        <a href="dashboard.php" class="inline-flex items-center px-4 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="stat-card border-blue-400">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Bookings</p>
            <p class="text-3xl font-bold text-gray-900"><?= number_format((int)($stats['total_bookings']??0)) ?></p>
        </div>
        <div class="stat-card border-primary-red">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Revenue</p>
            <p class="text-2xl font-bold text-primary-red">&#8358;<?= number_format((float)($stats['total_revenue']??0),2) ?></p>
            <p class="text-xs text-gray-400 mt-1">Paid bookings only</p>
        </div>
        <div class="stat-card border-green-500">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Confirmed</p>
            <p class="text-3xl font-bold text-green-700"><?= number_format((int)($stats['confirmed']??0)) ?></p>
        </div>
        <div class="stat-card border-yellow-400">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Pending</p>
            <p class="text-3xl font-bold text-yellow-600"><?= number_format((int)($stats['pending']??0)) ?></p>
        </div>
        <div class="stat-card border-red-400">
            <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Cancelled</p>
            <p class="text-3xl font-bold text-red-600"><?= number_format((int)($stats['cancelled']??0)) ?></p>
        </div>
    </div>



    <!-- Filters -->
    <form method="GET" class="card p-4 md:p-5 mb-6">
        <?php foreach ($selected_cols as $col): ?>
            <input type="hidden" name="cols[]" value="<?= htmlspecialchars($col) ?>">
        <?php endforeach; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="form-input">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Booking Status</label>
                <select name="booking_status" class="form-input">
                    <option value="">All Statuses</option>
                    <option value="pending"   <?= $booking_status==='pending'   ?'selected':'' ?>>Pending</option>
                    <option value="confirmed" <?= $booking_status==='confirmed' ?'selected':'' ?>>Confirmed</option>
                    <option value="cancelled" <?= $booking_status==='cancelled' ?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Status</label>
                <select name="payment_status" class="form-input">
                    <option value="">All</option>
                    <option value="pending"   <?= $payment_status==='pending'   ?'selected':'' ?>>Pending</option>
                    <option value="paid"      <?= $payment_status==='paid'      ?'selected':'' ?>>Paid</option>
                    <option value="cancelled" <?= $payment_status==='cancelled' ?'selected':'' ?>>Cancelled</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Method</label>
                <select name="payment_method" class="form-input">
                    <option value="">All Methods</option>
                    <?php foreach ($payment_methods_list as $pm): ?>
                        <option value="<?= htmlspecialchars($pm['raw']) ?>" <?= $payment_method===$pm['raw']?'selected':'' ?>>
                            <?= htmlspecialchars($pm['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Pickup City</label>
                <select name="pickup_city" class="form-input">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= $pickup_city==$city['id']?'selected':'' ?>><?= htmlspecialchars($city['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Dropoff City</label>
                <select name="dropoff_city" class="form-input">
                    <option value="">All Cities</option>
                    <?php foreach ($cities as $city): ?>
                        <option value="<?= $city['id'] ?>" <?= $dropoff_city==$city['id']?'selected':'' ?>><?= htmlspecialchars($city['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Search</label>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                    placeholder="PNR, name, email, phone, ref..." class="form-input">
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="submit" class="px-5 py-2 bg-primary-red text-white text-sm font-medium rounded-lg hover:bg-dark-red transition-colors">
                <i class="fas fa-search mr-2"></i>Apply Filters
            </button>
            <a href="analytics.php" class="px-5 py-2 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
                <i class="fas fa-times mr-2"></i>Clear
            </a>
        </div>
    </form>

    <!-- Table actions bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <p class="text-sm text-gray-600">
            Showing <strong><?= $total_rows>0?number_format(($page-1)*$per_page+1):0 ?>–<?= number_format(min($page*$per_page,$total_rows)) ?></strong>
            of <strong><?= number_format($total_rows) ?></strong> bookings
        </p>
        <div class="flex flex-wrap gap-2">
            <!-- Column picker -->
            <div class="relative">
                <button type="button" id="col-toggle"
                    class="inline-flex items-center gap-2 px-4 py-2 border border-gray-300 bg-white text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                    <i class="fas fa-columns text-primary-red"></i> Columns
                    <span class="bg-primary-red text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?= count($selected_cols) ?></span>
                </button>
                <div id="col-picker" class="absolute right-0 top-full mt-1 z-50 w-72 bg-white rounded-xl shadow-xl border border-gray-200 p-4">
                    <form method="GET">
                        <?php foreach (['date_from','date_to','booking_status','payment_status','payment_method','pickup_city','dropoff_city','search','page'] as $f): ?>
                            <?php if (!empty($$f)): ?><input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($$f) ?>"><?php endif; ?>
                        <?php endforeach; ?>
                        <p class="text-xs font-semibold text-gray-700 mb-3 uppercase tracking-wide">Table Display Columns</p>
                        <div class="space-y-2 max-h-72 overflow-y-auto pr-1">
                            <?php foreach ($all_fields as $key => $label): ?>
                                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:text-gray-900">
                                    <input type="checkbox" name="cols[]" value="<?= $key ?>" class="col-checkbox"
                                        <?= in_array($key,$selected_cols)?'checked':'' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="flex-1 py-2 bg-primary-red text-white text-xs font-medium rounded-lg hover:bg-dark-red">Apply</button>
                            <button type="button" id="col-cancel" class="flex-1 py-2 border border-gray-300 text-xs text-gray-600 rounded-lg hover:bg-gray-50">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Export button -->
            <button type="button" id="open-export-modal"
               class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors">
                <i class="fas fa-file-csv"></i> Export CSV
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <?php if (empty($bookings)): ?>
                <div class="text-center py-16">
                    <div class="mx-auto w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-4">
                        <i class="fas fa-inbox text-3xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">No Bookings Found</h3>
                    <p class="text-gray-500 text-sm">Try adjusting your filters.</p>
                </div>
            <?php else: ?>
                <table class="min-w-full">
                    <thead><tr>
                        <?php foreach ($selected_cols as $col): ?>
                            <th class="th"><?= htmlspecialchars($all_fields[$col]) ?></th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($bookings as $b): ?>
                            <tr class="tr">
                                <?php foreach ($selected_cols as $col): ?>
                                    <td class="td"><?php
                                        $val = $b[$col] ?? '';
                                        switch ($col) {
                                            case 'status':
                                            case 'payment_status':
                                                echo statusBadge((string)$val); break;
                                            case 'payment_method':
                                                echo methodBadge((string)$val); break;
                                            case 'total_amount':
                                                echo '<span class="font-semibold">&#8358;' . number_format((float)$val,2) . '</span>'; break;
                                            case 'free_booking_used':
                                                echo $val ? '<span class="text-green-600 text-xs font-medium"><i class="fas fa-gift mr-1"></i>Yes</span>' : '<span class="text-gray-400 text-xs">No</span>'; break;
                                            case 'departure_time': case 'arrival_time':
                                                echo htmlspecialchars($val ? date('h:i A', strtotime((string)$val)) : '—'); break;
                                            case 'trip_date':
                                                echo htmlspecialchars($val ? date('D, d M Y', strtotime((string)$val)) : '—'); break;
                                            case 'created_at': case 'payment_date':
                                                echo htmlspecialchars($val ? date('d M Y H:i', strtotime((string)$val)) : '—'); break;
                                            case 'transaction_reference': case 'gateway_reference':
                                                echo $val ? '<span class="font-mono text-xs bg-gray-100 px-1.5 py-0.5 rounded">' . htmlspecialchars((string)$val) . '</span>' : '<span class="text-gray-400">—</span>';
                                                break;
                                            case 'special_requests':
                                                $short = mb_strimwidth((string)$val, 0, 35, '...');
                                                echo $val ? '<span title="'.htmlspecialchars((string)$val).'" class="cursor-help">'.htmlspecialchars($short).'</span>' : '<span class="text-gray-400">—</span>';
                                                break;
                                            case 'pnr':
                                                echo '<span class="font-mono font-bold text-primary-red">' . htmlspecialchars((string)$val) . '</span>'; break;
                                            default:
                                                echo $val!==''&&$val!==null ? htmlspecialchars((string)$val) : '<span class="text-gray-400">—</span>';
                                        }
                                    ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-8">
        <p class="text-sm text-gray-500">Page <?= $page ?> of <?= $total_pages ?></p>
        <div class="flex gap-1 flex-wrap">
            <?php if ($page>1): ?>
                <a href="<?= qstr(['page'=>1]) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">«</a>
                <a href="<?= qstr(['page'=>$page-1]) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">‹</a>
            <?php endif; ?>
            <?php for ($p=max(1,$page-2); $p<=min($total_pages,$page+2); $p++): ?>
                <a href="<?= qstr(['page'=>$p]) ?>"
                   class="px-3 py-1.5 text-sm rounded-lg <?= $p===$page?'bg-primary-red text-white':'border border-gray-300 hover:bg-gray-50' ?>">
                   <?= $p ?></a>
            <?php endfor; ?>
            <?php if ($page<$total_pages): ?>
                <a href="<?= qstr(['page'=>$page+1]) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">›</a>
                <a href="<?= qstr(['page'=>$total_pages]) ?>" class="px-3 py-1.5 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">»</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>
</div>

<!-- EXPORT MODAL -->
<div id="export-modal" role="dialog" aria-modal="true">
    <div class="modal-overlay" id="export-overlay"></div>
    <div class="modal-box">
        <div class="flex items-center justify-between p-5 border-b border-gray-100">
            <div>
                <h2 class="text-lg font-bold text-gray-900"><i class="fas fa-file-csv text-green-600 mr-2"></i>Export Bookings to CSV</h2>
                <p class="text-xs text-gray-500 mt-0.5">Exporting <strong><?= number_format($total_rows) ?></strong> booking(s) with current filters.</p>
            </div>
            <button id="close-export-modal" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="GET" action="analytics.php">
            <?php foreach (['date_from','date_to','booking_status','payment_status','payment_method','pickup_city','dropoff_city','search'] as $f): ?>
                <?php if (!empty($$f)): ?><input type="hidden" name="<?= $f ?>" value="<?= htmlspecialchars($$f) ?>"><?php endif; ?>
            <?php endforeach; ?>
            <input type="hidden" name="export" value="csv">

            <div class="p-5 space-y-4">

                <!-- Default columns -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-check-circle text-green-500 mr-1"></i>Default Columns
                        </p>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Pre-selected</span>
                    </div>
                    <div class="section-block bg-green-50 border-green-200 grid grid-cols-2 gap-y-2 gap-x-3">
                        <?php foreach ($default_export_cols as $col): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="export_cols[]" value="<?= $col ?>"
                                    class="export-cb" style="accent-color:#16a34a" checked>
                                <?= htmlspecialchars($all_fields[$col]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment details -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-credit-card text-purple-500 mr-1"></i>Payment Details
                        </p>
                        <button type="button" class="sect-toggle text-xs text-purple-600 hover:text-purple-800 font-medium" data-target="sect-payment">Select all</button>
                    </div>
                    <div id="sect-payment" class="section-block bg-purple-50 border-purple-200 grid grid-cols-2 gap-y-2 gap-x-3">
                        <?php foreach (['transaction_reference','gateway_reference','payment_date'] as $col): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="export_cols[]" value="<?= $col ?>"
                                    class="export-cb opt-cb" style="accent-color:#7c3aed">
                                <?= htmlspecialchars($all_fields[$col]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Trip details -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-road text-blue-500 mr-1"></i>Trip Details
                        </p>
                        <button type="button" class="sect-toggle text-xs text-blue-600 hover:text-blue-800 font-medium" data-target="sect-trip">Select all</button>
                    </div>
                    <div id="sect-trip" class="section-block bg-blue-50 border-blue-200 grid grid-cols-2 gap-y-2 gap-x-3">
                        <?php foreach (['arrival_time','vehicle_type','vehicle_number','driver_name'] as $col): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="export_cols[]" value="<?= $col ?>"
                                    class="export-cb opt-cb" style="accent-color:#2563eb">
                                <?= htmlspecialchars($all_fields[$col]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Other -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            <i class="fas fa-ellipsis text-gray-500 mr-1"></i>Other
                        </p>
                        <button type="button" class="sect-toggle text-xs text-gray-600 hover:text-gray-800 font-medium" data-target="sect-other">Select all</button>
                    </div>
                    <div id="sect-other" class="section-block bg-gray-50 border-gray-200 grid grid-cols-2 gap-y-2 gap-x-3">
                        <?php foreach (['emergency_contact','special_requests','free_booking_used','created_at','user_email'] as $col): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                <input type="checkbox" name="export_cols[]" value="<?= $col ?>"
                                    class="export-cb opt-cb" style="accent-color:#4b5563">
                                <?= htmlspecialchars($all_fields[$col]) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

            <div class="flex gap-3 p-5 border-t border-gray-100 bg-gray-50 rounded-b-2xl">
                <button type="submit"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-2.5 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700 transition-colors text-sm">
                    <i class="fas fa-download"></i> Download CSV
                </button>
                <button type="button" id="cancel-export"
                    class="px-6 py-2.5 border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-white transition-colors">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Column picker
const colToggle = document.getElementById('col-toggle');
const colPicker = document.getElementById('col-picker');
colToggle.addEventListener('click', () => colPicker.classList.toggle('open'));
document.getElementById('col-cancel').addEventListener('click', () => colPicker.classList.remove('open'));
document.addEventListener('click', e => {
    if (!colToggle.contains(e.target) && !colPicker.contains(e.target)) colPicker.classList.remove('open');
});

// Export modal
const modal   = document.getElementById('export-modal');
const openM   = () => { modal.classList.add('open');    document.body.style.overflow='hidden'; };
const closeM  = () => { modal.classList.remove('open'); document.body.style.overflow=''; };
document.getElementById('open-export-modal').addEventListener('click', openM);
document.getElementById('close-export-modal').addEventListener('click', closeM);
document.getElementById('cancel-export').addEventListener('click', closeM);
document.getElementById('export-overlay').addEventListener('click', closeM);
document.addEventListener('keydown', e => { if(e.key==='Escape') closeM(); });
document.querySelector('.modal-box').addEventListener('click', e => e.stopPropagation());

// Per-section select all toggles
document.querySelectorAll('.sect-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
        const boxes = document.getElementById(btn.dataset.target).querySelectorAll('input[type=checkbox]');
        const allOn = [...boxes].every(cb => cb.checked);
        boxes.forEach(cb => cb.checked = !allOn);
        btn.textContent = allOn ? 'Select all' : 'Deselect all';
    });
});
</script>
</body>
</html>
<?php ob_end_flush(); ?>