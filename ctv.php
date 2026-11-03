<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/rate_limit.php';

$visitorId = getVisitorIdForLimit();
$ctvStatus = getCtvPublicStatus($visitorId);
$rawKey = $ctvStatus['key'];
$displayKey = $ctvStatus['display_key'];
$status = $ctvStatus['status'];

$daysLeft = null;
if (!empty($ctvStatus['expires_at'])) {
    $expireTs = strtotime($ctvStatus['expires_at'] . ' 23:59:59');
    if ($expireTs !== false) {
        $daysLeft = (int)floor(($expireTs - time()) / 86400);
        if ($daysLeft < 0) $daysLeft = 0;
    }
}

$statusConfig = [
    'pending' => [
        'badge' => 'Chờ kích hoạt',
        'badge_icon' => 'fa-clock',
        'hero_sub' => 'Copy key bên dưới gửi cho admin để được kích hoạt hạn sử dụng và tăng số lượt kích Gold mỗi ngày.',
        'title' => 'Chưa kích hoạt',
        'desc' => 'Key này chưa được admin duyệt. Gửi key cho admin để mở quyền CTV.',
        'key_label' => 'Key đăng ký CTV',
        'key_tag' => 'CHỜ DUYỆT',
        'btn_icon' => 'fa-regular fa-copy',
        'btn_text' => 'Copy key gửi admin',
        'note' => 'Dùng cùng trình duyệt này sau khi được kích hoạt.',
        'tips' => [
            'Key gắn với trình duyệt hiện tại — không xoá cookie / dữ liệu site.',
            'Gửi key cho admin, chờ kích hoạt xong rồi F5 lại trang này.',
            'Đổi trình duyệt hoặc máy khác sẽ ra key mới, cần kích hoạt lại.',
        ],
    ],
    'active' => [
        'badge' => 'CTV đã kích hoạt',
        'badge_icon' => 'fa-circle-check',
        'hero_sub' => 'Key của bạn đã được admin kích hoạt. Bạn đang dùng quyền CTV với quota cao hơn user thường.',
        'title' => 'Đã kích hoạt',
        'desc' => 'Quyền CTV đang hoạt động trên trình duyệt này. Vào trang chính để kích Gold.',
        'key_label' => 'Key đã kích hoạt',
        'key_tag' => 'ĐÃ KÍCH HOẠT',
        'btn_icon' => 'fa-solid fa-house',
        'btn_text' => 'Vào trang chính kích Gold',
        'note' => 'Không cần gửi lại key. Giữ nguyên trình duyệt này để giữ quyền CTV.',
        'tips' => [
            'Bạn đang có quota CTV cao hơn user thường.',
            'Không xoá cookie / dữ liệu site — mất key = mất quyền trên máy này.',
            'Sắp hết hạn thì nhờ admin gia hạn bằng đúng key này.',
        ],
    ],
    'expired' => [
        'badge' => 'Hết hạn',
        'badge_icon' => 'fa-hourglass-end',
        'hero_sub' => 'Hạn CTV đã hết. Copy key gửi admin để xin gia hạn lại.',
        'title' => 'Đã hết hạn',
        'desc' => 'Key vẫn còn nhưng quyền CTV đã hết hạn. Nhờ admin gia hạn để dùng tiếp.',
        'key_label' => 'Key đã hết hạn',
        'key_tag' => 'HẾT HẠN',
        'btn_icon' => 'fa-regular fa-copy',
        'btn_text' => 'Copy key xin gia hạn',
        'note' => 'Gửi lại key này cho admin để gia hạn, không cần lấy key mới.',
        'tips' => [
            'Key cũ vẫn dùng được để admin gia hạn — đừng đổi trình duyệt.',
            'Sau khi gia hạn, F5 trang này để thấy trạng thái Active.',
            'Trong lúc hết hạn bạn sẽ bị giới hạn như user thường (2 lượt/ngày).',
        ],
    ],
    'disabled' => [
        'badge' => 'Đã bị tắt',
        'badge_icon' => 'fa-ban',
        'hero_sub' => 'Quyền CTV đã bị admin tắt. Liên hệ admin nếu cần kích hoạt lại.',
        'title' => 'Đã bị tắt quyền',
        'desc' => 'Admin đã gỡ quyền CTV của key này. Liên hệ admin để được mở lại.',
        'key_label' => 'Key đã bị tắt',
        'key_tag' => 'ĐÃ TẮT',
        'btn_icon' => 'fa-regular fa-copy',
        'btn_text' => 'Copy key liên hệ admin',
        'note' => 'Key vẫn giữ nguyên — admin có thể kích hoạt lại nếu cần.',
        'tips' => [
            'Quyền bị tắt không xoá key — admin có thể bật lại.',
            'Giữ cùng trình duyệt để dùng lại đúng key này.',
            'Khi chưa có quyền CTV, bạn dùng mức user thường.',
        ],
    ],
];

$cfg = $statusConfig[$status] ?? $statusConfig['pending'];
$isActive = ($status === 'active');
$showCopy = ($status !== 'active');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Locket Gold · Đăng ký CTV</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="./assets/logoLocket.png"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --yellow: #F5C518;
            --yellow-deep: #E8A317;
            --amber: #B45309;
            --card: rgba(255, 255, 255, 0.88);
            --border: rgba(232, 163, 23, 0.24);
            --text: #1C1917;
            --text-soft: #78716C;
            --shadow: 0 20px 60px rgba(28, 25, 23, 0.08), 0 4px 16px rgba(232, 163, 23, 0.10);
        }

        @font-face {
            font-family: 'ProximaSoft';
            src: url('./assets/proxima_soft_bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            font-family: 'ProximaSoft', 'Outfit', system-ui, sans-serif;
            color: var(--text);
            background: #F7F4EF;
        }

        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background: linear-gradient(165deg, #FFFCFA 0%, #FAF7F2 38%, #F3EDE4 100%);
        }

        .bg-scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 55% at 8% -5%, rgba(255, 200, 120, 0.30) 0%, transparent 58%),
                radial-gradient(ellipse 70% 50% at 105% 15%, rgba(255, 228, 181, 0.36) 0%, transparent 52%);
        }

        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 18px;
        }

        .card {
            width: 100%;
            max-width: 460px;
            border-radius: 30px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            overflow: hidden;
            animation: cardPop 0.45s ease-out;
        }

        .card.state-active { border-color: rgba(22, 163, 74, 0.28); }
        .card.state-expired,
        .card.state-disabled { border-color: rgba(220, 38, 38, 0.22); }

        @keyframes cardPop {
            from { opacity: 0; transform: translateY(18px) scale(0.96); }
            to { opacity: 1; transform: none; }
        }

        .hero {
            padding: 28px 22px 20px;
            text-align: center;
            background: linear-gradient(135deg, #FFFFFF 0%, #FFFBEB 100%);
            border-bottom: 1px solid rgba(232, 163, 23, 0.16);
        }

        .card.state-active .hero {
            background: linear-gradient(135deg, #FFFFFF 0%, #ECFDF5 100%);
            border-bottom-color: rgba(22, 163, 74, 0.18);
        }

        .card.state-expired .hero,
        .card.state-disabled .hero {
            background: linear-gradient(135deg, #FFFFFF 0%, #FEF2F2 100%);
            border-bottom-color: rgba(220, 38, 38, 0.16);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: rgba(255,255,255,0.82);
            border: 1px solid rgba(232, 163, 23, 0.22);
            color: var(--amber);
            font-size: 12px;
            font-weight: 900;
        }

        .badge.active {
            color: #166534;
            border-color: #BBF7D0;
            background: #F0FDF4;
        }

        .badge.expired,
        .badge.disabled {
            color: #991B1B;
            border-color: #FECACA;
            background: #FEF2F2;
        }

        .brand .locket {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.2rem, 9vw, 3rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
        }

        .brand .gold {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.2rem, 9vw, 3rem);
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(120deg, #D97706 0%, #F59E0B 40%, #FBBF24 70%, #E8A317 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .subtitle {
            max-width: 340px;
            margin: 10px auto 0;
            color: var(--text-soft);
            font-size: 13.5px;
            line-height: 1.55;
        }

        .body { padding: 20px; }

        .status-box {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 20px;
            margin-bottom: 14px;
            border: 1px solid;
        }

        .status-box.pending { background: #FFFBEB; border-color: rgba(232,163,23,0.28); color: #92400E; }
        .status-box.active { background: #ECFDF5; border-color: #BBF7D0; color: #166534; }
        .status-box.expired,
        .status-box.disabled { background: #FEF2F2; border-color: #FECACA; color: #991B1B; }

        .status-icon {
            width: 42px; height: 42px; border-radius: 15px;
            display: grid; place-items: center;
            background: rgba(255,255,255,0.78); flex-shrink: 0;
            font-size: 18px;
        }

        .status-title { font-size: 14px; font-weight: 900; margin-bottom: 2px; }
        .status-desc { font-size: 12.5px; line-height: 1.4; opacity: 0.9; }

        .key-box {
            padding: 16px;
            border-radius: 20px;
            background: rgba(255,255,255,0.72);
            border: 1px solid rgba(232, 163, 23, 0.18);
        }

        .key-box.active {
            background: linear-gradient(180deg, #F0FDF4 0%, #FFFFFF 100%);
            border-color: #86EFAC;
            box-shadow: 0 10px 28px rgba(22, 163, 74, 0.10);
        }

        .key-box.expired,
        .key-box.disabled {
            background: linear-gradient(180deg, #FEF2F2 0%, #FFFFFF 100%);
            border-color: #FECACA;
        }

        .key-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            color: #92400E;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .key-box.active .key-label { color: #166534; }
        .key-box.expired .key-label,
        .key-box.disabled .key-label { color: #991B1B; }

        .key-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: 0.04em;
            background: #FFF7ED;
            border: 1px solid rgba(232,163,23,0.28);
            color: #B45309;
            white-space: nowrap;
        }

        .key-tag.active {
            background: #DCFCE7;
            border-color: #86EFAC;
            color: #166534;
        }

        .key-tag.expired,
        .key-tag.disabled {
            background: #FEE2E2;
            border-color: #FECACA;
            color: #991B1B;
        }

        .key-value-wrap {
            position: relative;
        }

        .key-value {
            width: 100%;
            padding: 16px 14px;
            border-radius: 14px;
            border: 1.5px dashed rgba(232, 163, 23, 0.40);
            background: #FFFCF5;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.04em;
            word-break: break-all;
            text-align: center;
            color: #1C1917;
            user-select: all;
        }

        .key-box.active .key-value {
            border: 2px solid #86EFAC;
            background: #F0FDF4;
            color: #14532D;
            padding-top: 22px;
            padding-bottom: 22px;
        }

        .key-box.expired .key-value,
        .key-box.disabled .key-value {
            border: 1.5px solid #FECACA;
            background: #FEF2F2;
            color: #7F1D1D;
            text-decoration: line-through;
            text-decoration-thickness: 1.5px;
            opacity: 0.85;
        }

        .key-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-12deg);
            pointer-events: none;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.12em;
            padding: 6px 12px;
            border-radius: 8px;
            border: 2px solid currentColor;
            text-transform: uppercase;
            opacity: 0.88;
            white-space: nowrap;
            background: rgba(255,255,255,0.72);
        }

        .key-stamp.active {
            color: #15803D;
            border-color: #22C55E;
        }

        .key-stamp.expired,
        .key-stamp.disabled {
            color: #B91C1C;
            border-color: #EF4444;
        }

        .action-btn {
            width: 100%;
            margin-top: 12px;
            padding: 14px;
            border: none;
            border-radius: 16px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            color: #1F2937;
            background: linear-gradient(135deg, #FFE259 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            box-shadow: 0 8px 26px rgba(245, 158, 11, 0.30);
        }

        .action-btn.active {
            color: #fff;
            background: linear-gradient(135deg, #22C55E 0%, #16A34A 100%);
            box-shadow: 0 8px 26px rgba(22, 163, 74, 0.28);
        }

        .action-btn.secondary {
            margin-top: 8px;
            color: #92400E;
            background: #fff;
            border: 1.5px solid rgba(232, 163, 23, 0.28);
            box-shadow: none;
        }

        .copy-note {
            margin-top: 10px;
            text-align: center;
            font-size: 12.5px;
            color: var(--text-soft);
            min-height: 18px;
            line-height: 1.45;
        }

        .copy-note.ok { color: #166534; font-weight: 800; }

        .meta {
            margin-top: 14px;
            padding: 13px;
            border-radius: 18px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
        }

        .meta.active {
            background: #F0FDF4;
            border-color: #BBF7D0;
        }

        .meta-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 900;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 8px;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            padding: 7px 0;
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
        }

        .meta.active .meta-row { border-bottom-color: rgba(187, 247, 208, 0.7); }
        .meta-row:last-child { border-bottom: none; }
        .meta-row span:first-child { color: #64748B; font-weight: 700; }
        .meta-row span:last-child { color: #1C1917; font-weight: 900; text-align: right; }

        .days-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            border-radius: 999px;
            background: #DCFCE7;
            color: #166534;
            font-size: 12px;
        }

        .days-pill.warn {
            background: #FEF3C7;
            color: #92400E;
        }

        .tips {
            margin-top: 14px;
            padding: 13px;
            border-radius: 18px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
        }

        .tips-title {
            display: flex; align-items: center; gap: 7px;
            color: #334155; font-size: 13px; font-weight: 900; margin-bottom: 7px;
        }

        .tips ul { margin: 0; padding-left: 18px; }
        .tips li { color: #64748B; font-size: 12px; line-height: 1.55; margin: 4px 0; }

        .home-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 14px;
            padding: 13px;
            border-radius: 16px;
            border: 1.5px solid rgba(232, 163, 23, 0.28);
            background: #fff;
            color: #92400E;
            text-decoration: none;
            font-weight: 900;
            font-size: 14px;
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            color: var(--text-soft);
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="bg-scene" aria-hidden="true"></div>

    <main class="page">
        <section class="card state-<?= htmlspecialchars($status) ?>">
            <div class="hero">
                <div class="badge <?= htmlspecialchars($status) ?>">
                    <i class="fa-solid <?= htmlspecialchars($cfg['badge_icon']) ?>"></i>
                    <?= htmlspecialchars($cfg['badge']) ?>
                </div>
                <div class="brand">
                    <div class="locket">Locket</div>
                    <div class="gold">Gold</div>
                </div>
                <p class="subtitle"><?= htmlspecialchars($cfg['hero_sub']) ?></p>
            </div>

            <div class="body">
                <div class="status-box <?= htmlspecialchars($status) ?>">
                    <div class="status-icon">
                        <i class="fa-solid <?= $status === 'active' ? 'fa-circle-check' : ($status === 'pending' ? 'fa-clock' : ($status === 'expired' ? 'fa-hourglass-end' : 'fa-ban')) ?>"></i>
                    </div>
                    <div>
                        <div class="status-title"><?= htmlspecialchars($cfg['title']) ?></div>
                        <div class="status-desc"><?= htmlspecialchars($cfg['desc']) ?></div>
                    </div>
                </div>

                <div class="key-box <?= htmlspecialchars($status) ?>">
                    <div class="key-label">
                        <span>
                            <i class="fa-solid fa-key"></i>
                            <?= htmlspecialchars($cfg['key_label']) ?>
                        </span>
                        <span class="key-tag <?= htmlspecialchars($status) ?>">
                            <?php if ($isActive): ?><i class="fa-solid fa-check"></i><?php endif; ?>
                            <?= htmlspecialchars($cfg['key_tag']) ?>
                        </span>
                    </div>

                    <div class="key-value-wrap">
                        <div class="key-value" id="ctvKey"><?= htmlspecialchars($displayKey) ?></div>
                        <?php if ($status !== 'pending'): ?>
                            <div class="key-stamp <?= htmlspecialchars($status) ?>">
                                <?= htmlspecialchars($cfg['key_tag']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($isActive): ?>
                        <a class="action-btn active" href="index.php">
                            <i class="<?= htmlspecialchars($cfg['btn_icon']) ?>"></i>
                            <?= htmlspecialchars($cfg['btn_text']) ?>
                        </a>
                        <button type="button" class="action-btn secondary" onclick="copyCtvKey()">
                            <i class="fa-regular fa-copy"></i>
                            Copy key (đã kích hoạt)
                        </button>
                    <?php else: ?>
                        <button type="button" class="action-btn" onclick="copyCtvKey()">
                            <i class="<?= htmlspecialchars($cfg['btn_icon']) ?>"></i>
                            <?= htmlspecialchars($cfg['btn_text']) ?>
                        </button>
                    <?php endif; ?>

                    <div class="copy-note" id="copyNote"><?= htmlspecialchars($cfg['note']) ?></div>
                </div>

                <?php if (in_array($status, ['active', 'expired', 'disabled'], true)): ?>
                    <div class="meta <?= $isActive ? 'active' : '' ?>">
                        <?php if ($isActive): ?>
                            <div class="meta-title">
                                <i class="fa-solid fa-id-badge"></i>
                                Thông tin quyền CTV
                            </div>
                        <?php endif; ?>

                        <div class="meta-row">
                            <span>Trạng thái</span>
                            <span><?= htmlspecialchars($cfg['title']) ?></span>
                        </div>
                        <div class="meta-row">
                            <span>Lượt / ngày</span>
                            <span><?= (int)($ctvStatus['daily_limit'] ?? 0) ?> lượt</span>
                        </div>
                        <div class="meta-row">
                            <span>Hết hạn</span>
                            <span><?= htmlspecialchars($ctvStatus['expires_at'] ?? '—') ?></span>
                        </div>
                        <?php if ($isActive && $daysLeft !== null): ?>
                            <div class="meta-row">
                                <span>Còn lại</span>
                                <span>
                                    <span class="days-pill <?= $daysLeft <= 3 ? 'warn' : '' ?>">
                                        <i class="fa-regular fa-calendar"></i>
                                        <?= $daysLeft ?> ngày
                                    </span>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ctvStatus['note'])): ?>
                            <div class="meta-row">
                                <span>Ghi chú</span>
                                <span><?= htmlspecialchars($ctvStatus['note']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($ctvStatus['activated_at'])): ?>
                            <div class="meta-row">
                                <span>Kích hoạt lúc</span>
                                <span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ctvStatus['activated_at']))) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="tips">
                    <div class="tips-title">
                        <i class="fa-solid fa-circle-info"></i>
                        Hướng dẫn
                    </div>
                    <ul>
                        <?php foreach ($cfg['tips'] as $tip): ?>
                            <li><?= htmlspecialchars($tip) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if (!$isActive): ?>
                    <a class="home-btn" href="index.php">
                        <i class="fa-solid fa-house"></i>
                        Về trang chính
                    </a>
                <?php endif; ?>

                <div class="footer">© 2026 Locket Gold · CTV</div>
            </div>
        </section>
    </main>

    <script>
        const RAW_KEY = <?= json_encode($rawKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const DISPLAY_KEY = <?= json_encode($displayKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const STATUS = <?= json_encode($status, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        async function copyCtvKey() {
            const note = document.getElementById('copyNote');
            const text = DISPLAY_KEY || RAW_KEY;

            try {
                await navigator.clipboard.writeText(text);
                note.className = 'copy-note ok';
                if (STATUS === 'active') {
                    note.innerHTML = '<i class="fa-solid fa-circle-check"></i> Đã copy key đã kích hoạt.';
                } else if (STATUS === 'expired') {
                    note.innerHTML = '<i class="fa-solid fa-circle-check"></i> Đã copy. Gửi admin để xin gia hạn.';
                } else if (STATUS === 'disabled') {
                    note.innerHTML = '<i class="fa-solid fa-circle-check"></i> Đã copy. Gửi admin để xin mở lại.';
                } else {
                    note.innerHTML = '<i class="fa-solid fa-circle-check"></i> Đã copy key. Gửi cho admin để kích hoạt.';
                }
            } catch (e) {
                const range = document.createRange();
                range.selectNodeContents(document.getElementById('ctvKey'));
                const sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(range);
                note.textContent = 'Giữ để copy thủ công nếu trình duyệt chặn clipboard.';
            }
        }
    </script>
</body>
</html>
