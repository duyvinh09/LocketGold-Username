<?php
require_once __DIR__ . '/maintenance_check.php';
requireNotMaintenance();

require_once __DIR__ . '/auth.php';
$csrfToken = LocketAuth::issueCsrfToken();
$mobileConfigUrl = defined('LOCKET_MOBILECONFIG_URL') ? LOCKET_MOBILECONFIG_URL : '/assets/LocketGold.mobileconfig';
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locket Gold · Kích hoạt qua Username</title>
    <meta name="description" content="Nâng cấp Locket Gold bằng username — nhanh, gọn, Gen Z.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="./assets/logoLocket.png"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            --yellow: #F5C518;
            --yellow-soft: #FFFCF7;
            --yellow-mid: #FFF8EB;
            --yellow-deep: #E8A317;
            --amber: #B45309;
            --card: rgba(255, 255, 255, 0.88);
            --border: rgba(232, 163, 23, 0.22);
            --border-strong: rgba(232, 163, 23, 0.38);
            --text: #1C1917;
            --text-soft: #78716C;
            --text-warm: #92400E;
            --green: #16A34A;
            --red: #DC2626;
            --radius: 22px;
            --radius-sm: 14px;
            --shadow: 0 16px 48px rgba(28, 25, 23, 0.06), 0 2px 8px rgba(232, 163, 23, 0.08);
        }

        @font-face {
            font-family: 'ProximaSoft';
            src: url('./assets/proxima_soft_bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        /* --- PRELOADER LOCKET GOLD --- */
        .preloader {
            position: fixed;
            inset: 0;
            z-index: 20000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 22px;
            background:
                radial-gradient(circle at 20% 10%, rgba(255, 214, 120, 0.35), transparent 34%),
                radial-gradient(circle at 85% 20%, rgba(255, 240, 190, 0.55), transparent 32%),
                linear-gradient(165deg, #FFFCFA 0%, #FAF7F2 42%, #F3EDE4 100%);
            transition: opacity 0.45s ease, visibility 0.45s ease;
        }

        .preloader.hide {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }

        .preloader-card {
            width: 100%;
            max-width: 360px;
            padding: 28px 22px 24px;
            border-radius: 28px;
            text-align: center;
            background: rgba(255, 255, 255, 0.82);
            border: 1px solid rgba(232, 163, 23, 0.24);
            box-shadow:
                0 24px 70px rgba(28, 25, 23, 0.10),
                0 4px 16px rgba(232, 163, 23, 0.12);
            backdrop-filter: blur(16px);
            position: relative;
            overflow: hidden;
            animation: preloaderPop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .preloader-card::before {
            content: '';
            position: absolute;
            width: 190px;
            height: 190px;
            border-radius: 50%;
            top: -90px;
            right: -80px;
            background: rgba(245, 197, 24, 0.18);
            filter: blur(4px);
        }

        .preloader-card::after {
            content: '';
            position: absolute;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            bottom: -80px;
            left: -70px;
            background: rgba(255, 183, 77, 0.16);
            filter: blur(6px);
        }

        .preloader-inner {
            position: relative;
            z-index: 1;
        }

        .preloader-logo-wrap {
            width: 84px;
            height: 84px;
            margin: 0 auto 16px;
            position: relative;
            display: grid;
            place-items: center;
        }

        .preloader-logo-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(245, 197, 24, 0.25);
            border-top-color: #E8A317;
            animation: preloaderSpin 1.1s linear infinite;
        }

        .preloader-logo {
            width: 64px;
            height: 64px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: linear-gradient(145deg, #FFE566 0%, #F5C518 52%, #E8A317 100%);
            color: #7C2D12;
            font-size: 28px;
            box-shadow: 0 12px 34px rgba(232, 163, 23, 0.35);
            animation: heartBeatGold 1.35s ease-in-out infinite;
        }

        .preloader-title {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            margin-bottom: 10px;
        }

        .preloader-title .locket-text {
            font-family: 'Syne', sans-serif;
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
            color: #1C1917;
        }

        .preloader-title .gold-text {
            font-family: 'Syne', sans-serif;
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
            background: linear-gradient(120deg, #D97706 0%, #F59E0B 38%, #FBBF24 72%, #E8A317 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 10px rgba(245, 158, 11, 0.22));
        }

        .preloader-subtitle {
            color: #78716C;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 18px;
        }

        .preloader-progress {
            height: 9px;
            width: 100%;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(232, 163, 23, 0.14);
            border: 1px solid rgba(232, 163, 23, 0.16);
            margin-bottom: 12px;
        }

        .preloader-progress-fill {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, #FFE566, #F5C518, #E8A317);
            box-shadow: 0 0 18px rgba(245, 197, 24, 0.55);
            animation: preloaderLoad 1.65s ease-in-out forwards;
        }

        .preloader-status {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 12px;
            border-radius: 999px;
            background: #FFFBEB;
            border: 1px solid rgba(232, 163, 23, 0.18);
            color: #92400E;
            font-size: 12px;
            font-weight: 700;
        }

        .preloader-dots {
            display: inline-flex;
            gap: 3px;
        }

        .preloader-dots span {
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: #E8A317;
            animation: dotBounce 1s ease-in-out infinite;
        }

        .preloader-dots span:nth-child(2) {
            animation-delay: 0.15s;
        }

        .preloader-dots span:nth-child(3) {
            animation-delay: 0.3s;
        }

        @keyframes preloaderPop { from { opacity: 0; transform: translateY(18px) scale(0.96); } to { opacity: 1; transform: none; } }
        @keyframes preloaderSpin { to { transform: rotate(360deg); } }
        @keyframes heartBeatGold { 0%, 100% { transform: scale(1); } 45% { transform: scale(1.08); } }
        @keyframes preloaderLoad { 0% { width: 0%; } 45% { width: 58%; } 78% { width: 86%; } 100% { width: 100%; } }
        @keyframes dotBounce { 0%, 80%, 100% { transform: translateY(0); opacity: 0.45; } 40% { transform: translateY(-4px); opacity: 1; } }

        body {
            font-family: 'ProximaSoft', system-ui, sans-serif;
            background: #F7F4EF;
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
            background:
                linear-gradient(165deg, #FFFCFA 0%, #FAF7F2 38%, #F3EDE4 100%);
        }

        .bg-scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 55% at 8% -5%, rgba(255, 200, 120, 0.28) 0%, transparent 58%),
                radial-gradient(ellipse 70% 50% at 105% 15%, rgba(255, 228, 181, 0.32) 0%, transparent 52%),
                radial-gradient(ellipse 55% 45% at 50% 110%, rgba(253, 230, 138, 0.18) 0%, transparent 55%),
                radial-gradient(ellipse 40% 35% at 85% 75%, rgba(255, 241, 213, 0.45) 0%, transparent 50%);
        }

        .bg-scene::after {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0.35;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            background-size: 180px 180px;
        }

        .bg-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(50px);
            animation: drift 18s ease-in-out infinite;
        }

        .bg-orb-1 {
            width:  min(420px, 90vw);
            height: min(420px, 90vw);
            top: -12%;
            right: -18%;
            background: rgba(255, 183, 77, 0.22);
        }

        .bg-orb-2 {
            width: min(340px, 75vw);
            height: min(340px, 75vw);
            bottom: -8%;
            left: -22%;
            background: rgba(255, 214, 153, 0.2);
            animation-delay: -6s;
        }

        .bg-orb-3 {
            width: 200px;
            height: 200px;
            top: 42%;
            left: 55%;
            background: rgba(255, 248, 235, 0.9);
            filter: blur(10px);
            animation-delay: -11s;
            opacity: 0.7;
        }

        @keyframes drift {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(-12px, 8px) scale(1.03); }
            66% { transform: translate(10px, -6px) scale(0.98); }
        }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 440px;
            margin: 0 auto;
            padding: 32px 18px 48px;
        }

        .hero {
            text-align: center;
            margin-bottom: 28px;
            padding: 0 4px;
        }

        .logo-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 16px;
            border-radius: 18px;
            background: linear-gradient(145deg, #FFE566 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            display: grid;
            place-items: center;
            font-size: 26px;
            color: #7C2D12;
            box-shadow: 0 10px 32px rgba(232, 163, 23, 0.28);
        }

        .badge-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            font-size: 11px;
            font-weight: 700;
            color: var(--amber);
            margin-bottom: 18px;
        }

        .brand-title {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            margin: 0 0 12px;
            padding: 0;
        }

        .brand-locket {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.5rem, 12vw, 3.5rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--text);
        }

        .brand-gold {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.5rem, 12vw, 3.5rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
            background: linear-gradient(120deg, #D97706 0%, #F59E0B 40%, #FBBF24 70%, #F59E0B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 12px rgba(245, 158, 11, 0.25));
        }

        .hero p {
            font-size: 14px;
            color: var(--text-soft);
            line-height: 1.6;
            max-width: 320px;
            margin: 0 auto;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px;
            margin-bottom: 14px;
            box-shadow: var(--shadow);
        }

        .card-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--text-warm);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-label i { color: var(--yellow-deep); }

        .search-row { display: flex; gap: 10px; }

        .input-wrap { flex: 1; position: relative; }

        .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--amber);
            font-size: 14px;
        }

        .search-input {
            width: 100%;
            padding: 13px 14px 13px 40px;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--border);
            background: rgba(255, 255, 255, 0.85);
            color: var(--text);
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .search-input::placeholder { color: #A8A29E; }

        .search-input:focus {
            border-color: var(--yellow-deep);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(250, 204, 21, 0.35);
        }

        .btn-search {
            padding: 0 18px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            color: #1F2937;
            background: linear-gradient(135deg, #FFE259 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
            transition: transform 0.15s, box-shadow 0.15s;
        }

        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(245, 158, 11, 0.4); }
        .btn-search:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        .hint { margin-top: 10px; font-size: 12px; color: var(--text-soft); }

        .hint kbd {
            padding: 2px 7px;
            border-radius: 6px;
            background: var(--yellow-mid);
            border: 1px solid var(--border);
            font-size: 10px;
            color: var(--text-warm);
        }

        .welcome {
            text-align: center;
            padding: 28px 8px;
            color: var(--text-soft);
        }

        .welcome .emoji { font-size: 44px; margin-bottom: 10px; display: block; }
        .welcome p { font-size: 14px; line-height: 1.55; }

        .profile-card { animation: pop 0.3s ease-out; }

        @keyframes pop {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: none; }
        }

        .profile-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
            padding-bottom: 14px;
            border-bottom: 1px dashed var(--border);
        }

        .avatar {
            width: 68px;
            height: 68px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--yellow);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }

        .profile-name { font-size: 17px; font-weight: 800; color: var(--text); }

        .profile-user {
            font-size: 14px;
            color: var(--amber);
            font-weight: 600;
            margin-top: 2px;
        }

        .gold-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--border-strong);
            font-size: 11px;
            font-weight: 700;
            color: var(--text-warm);
        }

        .gold-chip i { color: #E11D48; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .info-cell {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 11px 12px;
        }

        .info-cell.full { grid-column: 1 / -1; }

        .info-key {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-warm);
            font-weight: 700;
            margin-bottom: 4px;
        }

        .info-val {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            word-break: break-all;
        }

        .gold-zone {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px dashed var(--border-strong);
        }

        .gold-zone h3 {
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-warm);
        }

        .gold-zone h3 i { color: var(--yellow-deep); }

        .status-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 11px 14px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 12px;
            width: 100%;
        }

        .status-pill.ok {
            background: #DCFCE7;
            border: 1px solid #86EFAC;
            color: var(--green);
        }

        .status-pill.no {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: var(--red);
        }

        .status-pill.load {
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid var(--border);
            color: var(--text-soft);
        }

        .btn-gold {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: var(--radius-sm);
            font-family: inherit;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            color: #1F2937;
            background: linear-gradient(135deg, #FFD700, var(--yellow), var(--yellow-deep));
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 32px rgba(245, 158, 11, 0.35);
            transition: transform 0.15s;
        }

        .btn-gold:hover:not(:disabled) { transform: translateY(-2px); }
        .btn-gold:disabled { opacity: 0.55; cursor: not-allowed; }

        .progress-box {
            margin-top: 12px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
        }

        .progress-bar {
            height: 8px;
            background: rgba(232, 163, 23, 0.15);
            border-radius: 999px;
            overflow: hidden;
            margin-bottom: 12px;
        }

        .progress-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--yellow), var(--yellow-deep));
            border-radius: 999px;
            transition: none;
        }

        .progress-steps { list-style: none; }

        .progress-steps li {
            font-size: 12px;
            color: var(--text-soft);
            padding: 5px 0 5px 26px;
            position: relative;
        }

        .progress-steps li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid #E5E7EB;
            background: #fff;
        }

        .progress-steps li.active { color: var(--text); font-weight: 600; }
        .progress-steps li.active::before {
            border-color: var(--yellow-deep);
            background: var(--yellow);
        }

        .progress-steps li.done { color: var(--green); }
        .progress-steps li.done::before {
            border-color: var(--green);
            background: var(--green);
            content: '✓';
            font-size: 8px;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 14px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
        }
        .progress-pct {
            color: var(--amber);
            background: var(--yellow-mid);
            padding: 2px 8px;
            border-radius: 6px;
            border: 1px solid var(--border);
            font-variant-numeric: tabular-nums;
        }
        .step-timer {
            float: right;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-soft);
            background: rgba(255,255,255,0.8);
            padding: 1px 6px;
            border-radius: 4px;
            font-variant-numeric: tabular-nums;
        }
        .step-timer i { color: var(--green); }
        .queue-timer-box {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        .queue-timer-box .q-text { display: flex; align-items: center; gap: 8px; }
        .queue-timer-box .q-time { font-size: 12px; color: var(--amber); font-weight: 700; background: var(--yellow-mid); padding: 2px 8px; border-radius: 6px;}

        .success-block {
            margin-top: 12px;
            padding: 14px;
            border-radius: var(--radius-sm);
            background: linear-gradient(135deg, #ECFDF5, #FFFBEB);
            border: 1px solid #86EFAC;
        }

        .success-block h4 {
            font-size: 14px;
            font-weight: 800;
            color: var(--green);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .date-row { font-size: 13px; margin: 6px 0; }
        .date-row span { color: var(--text-soft); font-size: 11px; display: block; margin-bottom: 2px; }

        .btn-config {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            margin-top: 12px;
            padding: 13px;
            border-radius: var(--radius-sm);
            background: #fff;
            border: 1.5px solid var(--border-strong);
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: background 0.2s, transform 0.15s;
        }

        .btn-config:hover {
            background: var(--yellow-soft);
            transform: translateY(-1px);
        }

        .btn-config i { color: var(--amber); font-size: 18px; }

        .config-note {
            margin-top: 10px;
            font-size: 11px;
            color: var(--text-soft);
            line-height: 1.45;
            text-align: center;
        }

        .error-box {
            padding: 14px;
            border-radius: var(--radius-sm);
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: var(--red);
            font-size: 13px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid var(--yellow-mid);
            border-top-color: var(--yellow-deep);
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: var(--text-soft);
        }

        .loading-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 32px;
            color: var(--text-soft);
            font-size: 14px;
        }

        .avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        
        /* --- STYLE HUY HIỆU ĐÈ LÊN AVATAR CHUẨN LOCKET --- */
        .avatar-badge {
            position: absolute;
            bottom: 3px;
            right: 3px;
            width: 22px;
            height: 22px;
            background: #F5C518;
            border: 2px solid #FFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #FFFDF0;
            z-index: 10;
            animation: pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .status-pill-locket-gold {
            background: #FFFDF0;
            border: 2px solid #F5C518;
            border-radius: 24px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-align: left;
            box-shadow: var(--shadow);
            animation: pop 0.4s ease-out;
            margin-bottom: 12px;
        }
        
        .locket-gold-icon-box {
            width: 44px; height: 44px;
            background: rgba(245, 197, 24, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            display: grid; place-items: center;
            color: #F5C518; font-size: 18px;
        }
        
        .locket-gold-text-box { flex: 1; }
        .locket-gold-title { font-size: 15px; font-weight: 700; color: #1C1917; }
        .locket-gold-subtitle { font-size: 13px; color: #78716C; margin-top: 2px; }
        .locket-gold-arrow { color: #78716C; font-size: 14px; }

        /* --- STYLE CHO POPUP --- */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(28, 25, 23, 0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.2s ease-out;
        }

        .modal-box {
            background: #ffffff;
            width: 100%;
            max-width: 390px;
            border-radius: 24px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15);
            border: 1px solid rgba(232, 163, 23, 0.2);
            animation: popUp 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .modal-heart {
            width: 50px;
            height: 50px;
            background: #FEF3C7;
            color: #D97706;
            font-size: 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            margin: 0 auto 14px;
        }

        .modal-box h2 {
            font-size: 19px;
            font-weight: 800;
            color: #1C1917;
            margin-bottom: 16px;
        }

        .modal-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 20px;
            background: #F5F2EB;
            padding: 4px;
            border-radius: 14px;
            border: 1px solid rgba(232, 163, 23, 0.1);
        }

        .tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 700;
            color: #78716C;
            cursor: pointer;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .tab-btn.active {
            background: #ffffff;
            color: #1C1917;
            box-shadow: 0 4px 12px rgba(28, 25, 23, 0.05);
        }

        .tab-content {
            animation: fadeIn 0.25s ease-out;
            min-height: 110px;
        }

        .tab-content p {
            font-size: 13px;
            color: #78716C;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .follow-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 8px;
        }

        .btn-follow {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border-radius: 14px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            transition: transform 0.15s;
        }

        .btn-follow:hover {
            transform: translateY(-2px);
        }

        .btn-follow.tiktok {
            background: #000000;
        }

        .btn-follow.insta {
            background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
        }

        .qr-container {
            background: #ffffff;
            padding: 12px;
            border-radius: 18px;
            border: 2px dashed rgba(232, 163, 23, 0.3);
            margin: 0 auto 10px;
            max-width: 190px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.02);
        }

        .qr-container img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
        }

        .btn-confirm {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 14px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 800;
            cursor: pointer;
            color: #1F2937;
            background: linear-gradient(135deg, #FFD700, var(--yellow));
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.2);
            transition: all 0.2s;
            margin-top: 10px;
        }

        .btn-confirm:disabled {
            background: #E5E7EB;
            color: #9CA3AF;
            cursor: not-allowed;
            box-shadow: none;
            opacity: 0.7;
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes popUp { from { transform: scale(0.9) translateY(20px); opacity: 0; } to { transform: scale(1) translateY(0); opacity: 1; } }

        /* --- CSS CHO KHUNG VIDEO HƯỚNG DẪN --- */
        .activation-guide-card {
            margin: 20px 0 14px;
            padding: 16px;
            border-radius: 20px;
            border: 1px solid rgba(232, 163, 23, 0.18);
            background: rgba(255, 251, 235, 0.82);
            text-align: left;
            box-shadow: 0 8px 24px rgba(28, 25, 23, 0.04);
        }

        .activation-guide-head {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 15px;
            font-weight: 800;
            color: #1C1917;
            margin-bottom: 10px;
        }

        .activation-guide-head i {
            color: var(--yellow-deep);
        }

        .activation-guide-list {
            margin: 0;
            padding-left: 18px;
            color: #57534E;
            font-size: 13px;
            line-height: 1.65;
        }

        .activation-guide-list strong {
            color: #1C1917;
        }

        .activation-guide-note {
            margin: 12px 0 0;
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.75);
            color: #78716C;
            font-size: 12.5px;
            line-height: 1.55;
        }

        .locket-warning-card {
            margin-top: 14px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(239, 68, 68, 0.18);
            background: rgba(254, 242, 242, 0.85);
        }

        .locket-warning-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #991B1B;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .locket-warning-list {
            margin: 0;
            padding-left: 18px;
            color: #7F1D1D;
            font-size: 13px;
            line-height: 1.65;
        }

        .locket-warning-list strong {
            color: #991B1B;
        }

        .how-it-works-card {
            margin-top: 14px;
            padding: 14px;
            border-radius: 18px;
            border: 1px solid rgba(59, 130, 246, 0.18);
            background: rgba(239, 246, 255, 0.9);
        }

        .how-it-works-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1D4ED8;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .how-it-works-list {
            margin: 0;
            padding-left: 18px;
            color: #1E3A8A;
            font-size: 13px;
            line-height: 1.65;
        }

        .how-it-works-list strong {
            color: #1D4ED8;
        }

        .how-it-works-note {
            margin-top: 12px;
            padding: 10px 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.72);
            color: #334155;
            font-size: 12.5px;
            line-height: 1.55;
        }

        .privacy-mini-list {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .privacy-mini-list li {
            margin: 4px 0;
        }

        .video-guide-zone {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed rgba(232, 163, 23, 0.2);
            text-align: left;
        }
        
        .video-title {
            font-size: 13px;
            font-weight: 700;
            color: #1C1917;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .video-title i {
            color: #F5C518;
            font-size: 14px;
        }
        
        .video-wrapper {
            position: relative;
            width: 100%;
            max-width: 260px;
            margin: 0 auto;
            aspect-ratio: 9 / 16;
            border-radius: 20px;
            overflow: hidden;
            border: 1.5px solid rgba(232, 163, 23, 0.15);
            box-shadow: 0 8px 24px rgba(0,0,0,0.02);
            background: #000000;
        }
        
        .video-wrapper video, 
        .video-wrapper iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
            object-fit: cover;
        }

        /* --- STYLE CHO LIVE STATS (HÀNG ĐỢI) --- */
        .live-stats {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
            padding: 0 10px;
        }

        .stat-pill {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(8px);
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-soft);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(28, 25, 23, 0.03);
        }

        .stat-pill strong {
            color: var(--text);
            font-size: 13px;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.green {
            background: var(--green);
            animation: pulse-green 2s infinite;
        }

        .dot.yellow {
            background: var(--yellow-deep);
            animation: pulse-yellow 2s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(22, 163, 74, 0); }
            100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
        }

        @keyframes pulse-yellow {
            0% { box-shadow: 0 0 0 0 rgba(232, 163, 23, 0.4); }
            70% { box-shadow: 0 0 0 6px rgba(232, 163, 23, 0); }
            100% { box-shadow: 0 0 0 0 rgba(232, 163, 23, 0); }
        }

        .limit-card {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin: -2px 0 16px;
        }

        .limit-mini-box {
            position: relative;
            overflow: hidden;
            min-height: 118px;
            padding: 14px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.78);
            border: 1px solid rgba(232, 163, 23, 0.22);
            box-shadow: 0 10px 30px rgba(28, 25, 23, 0.045);
            backdrop-filter: blur(12px);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }

        .limit-mini-box::before {
            content: '';
            position: absolute;
            width: 115px;
            height: 115px;
            border-radius: 50%;
            right: -55px;
            top: -55px;
            background: rgba(245, 197, 24, 0.16);
        }

        .limit-mini-box::after {
            content: '';
            position: absolute;
            width: 78px;
            height: 78px;
            border-radius: 50%;
            left: -42px;
            bottom: -42px;
            background: rgba(255, 183, 77, 0.13);
        }

        .limit-mini-box:hover {
            border-color: rgba(232, 163, 23, 0.36);
            box-shadow: 0 14px 36px rgba(28, 25, 23, 0.07);
        }

        .limit-mini-box.used {
            background:
                radial-gradient(circle at 100% 0%, rgba(245, 197, 24, 0.18), transparent 38%),
                rgba(255, 255, 255, 0.78);
        }

        .limit-mini-box.remaining {
            background:
                radial-gradient(circle at 100% 0%, rgba(22, 163, 74, 0.12), transparent 38%),
                rgba(255, 255, 255, 0.78);
        }

        .limit-icon {
            position: relative;
            z-index: 1;
            width: 38px;
            height: 38px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            margin-bottom: 10px;
            background: #FFFBEB;
            border: 1px solid rgba(232, 163, 23, 0.2);
            color: var(--yellow-deep);
            font-size: 15px;
            box-shadow: 0 6px 18px rgba(232, 163, 23, 0.12);
        }

        .limit-mini-box.remaining .limit-icon {
            background: #ECFDF5;
            border-color: rgba(22, 163, 74, 0.18);
            color: var(--green);
        }

        .limit-content {
            position: relative;
            z-index: 1;
        }

        .limit-mini-label {
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--text-warm);
            margin-bottom: 4px;
        }

        .limit-mini-value {
            display: flex;
            align-items: baseline;
            gap: 5px;
            font-size: 28px;
            line-height: 1;
            font-weight: 900;
            color: var(--text);
            letter-spacing: -0.03em;
        }

        .limit-mini-value small {
            font-size: 11px;
            font-weight: 800;
            color: var(--text-soft);
            letter-spacing: 0;
        }

        .limit-mini-desc {
            margin-top: 7px;
            font-size: 11.5px;
            line-height: 1.35;
            color: var(--text-soft);
        }

        .limit-badge {
            position: absolute;
            z-index: 1;
            top: 12px;
            right: 12px;
            padding: 4px 8px;
            border-radius: 999px;
            background: rgba(255, 251, 235, 0.9);
            border: 1px solid rgba(232, 163, 23, 0.18);
            color: var(--amber);
            font-size: 9.5px;
            font-weight: 900;
        }

        .limit-mini-box.remaining .limit-badge {
            background: rgba(236, 253, 245, 0.9);
            border-color: rgba(22, 163, 74, 0.16);
            color: var(--green);
        }

        @media (max-width: 380px) {
            .limit-card {
                grid-template-columns: 1fr;
            }

            .limit-mini-box {
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <div id="pagePreloader" class="preloader">
        <div class="preloader-card">
            <div class="preloader-inner">
                <div class="preloader-logo-wrap">
                    <div class="preloader-logo-ring"></div>
                    <div class="preloader-logo">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                </div>

                <div class="preloader-title">
                    <span class="locket-text">Locket</span>
                    <span class="gold-text">Gold</span>
                </div>

                <p class="preloader-subtitle">
                    Đợi mình load tí...
                </p>

                <div class="preloader-progress">
                    <div class="preloader-progress-fill"></div>
                </div>

                <div class="preloader-status">
                    <i class="fa-solid fa-sparkles"></i>
                    <span>Đang tải hệ thống</span>
                    <span class="preloader-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-scene" aria-hidden="true">
        <div class="bg-orb bg-orb-1"></div>
        <div class="bg-orb bg-orb-2"></div>
        <div class="bg-orb bg-orb-3"></div>
    </div>

    <div class="wrap">
        <header class="hero">
            <div class="logo-icon"><i class="fa-solid fa-heart"></i></div>
            <div class="badge-pill"><i class="fa-solid fa-sparkles"></i>@duyvinh09</div>
            <h1 class="brand-title">
                <span class="brand-locket">Locket</span>
                <span class="brand-gold">Gold</span>
            </h1>
            <p>"Thả nhẹ cái tên, rước ngay Gold xịn"</p>
        </header>

        <div class="live-stats">
            <div class="stat-pill">
                <span class="dot green"></span>
                <span>Online: <strong id="countOnline">--</strong></span>
            </div>

            <div class="stat-pill">
                <span class="dot yellow"></span>
                <span>Đang xử lý: <strong id="countActivating">--</strong></span>
            </div>
        </div>

        <section class="card">
            <div class="card-label"><i class="fa-solid fa-at"></i> Username hoặc Link Locket</div>
            <div class="search-row">
                <div class="input-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="username" class="search-input" placeholder="duyvinh09 hoặc link locket.camera/links/..." autocomplete="off">
                </div>
                <button type="button" class="btn-search" id="searchBtn" onclick="searchUser()">
                    <i class="fa-solid fa-bolt"></i> Tìm
                </button>
            </div>
            <p class="hint">Nhập @username, link profile hoặc link copy <code>locket.camera/links/...</code> rồi nhấn <kbd>Enter</kbd></p>
        </section>

        <div class="limit-card">
            <div class="limit-mini-box used">
                <div class="limit-icon">
                    <i class="fa-solid fa-bolt"></i>
                </div>

                <div class="limit-content">
                    <div class="limit-mini-label">Đã kích hoạt</div>
                    <div class="limit-mini-value">
                        <span id="countTodaySuccess">--</span>
                        <small>lượt</small>
                    </div>
                    <div class="limit-mini-desc">Số lượt thành công hôm nay</div>
                </div>

                <div class="limit-badge">Hôm nay</div>
            </div>

            <div class="limit-mini-box remaining">
                <div class="limit-icon">
                    <i class="fa-solid fa-ticket"></i>
                </div>

                <div class="limit-content">
                    <div class="limit-mini-label">Còn lại</div>
                    <div class="limit-mini-value">
                        <span id="countTodayRemaining">--</span>
                        <small>lượt</small>
                    </div>
                    <div class="limit-mini-desc">Lượt kích hoạt có thể dùng</div>
                </div>

                <div class="limit-badge">Limit</div>
            </div>
        </div>

        <section class="card" id="result">
            <div class="welcome">
                <span class="emoji">💛</span>
                <p>Nhập username Locket để xem thông tin<br>rồi kích hoạt Gold nha~</p>
            </div>
        </section>

        <div id="publicActivationGuide"></div>

        <p class="footer">© 2026 Locket Gold · duyvinh09</p>
    </div>

    <script>
        let CSRF_TOKEN = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const MOBILECONFIG_URL = <?= json_encode($mobileConfigUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const GATEWAY = 'gateway.php';

        const searchBtn = document.getElementById('searchBtn');
        const usernameInput = document.getElementById('username');
        const resultDiv = document.getElementById('result');

        let currentUid = null;
        let isNewIp = false;
        let isFirstOfDay = false;
        let isLimitAdmin = false;
        let isLimitCtv = false;
        let todaySuccessCount = 0;
        let todayRemaining = 2;
        let hasClickedFollow = false;
        let currentTab = 'free';
        let clickedTiktok = false;
        let clickedInsta = false;

        // Khôi phục giá trị đã lưu gần nhất để không bao giờ bị hiện "--" khi quay lại trang
        function restoreStatsFromLocalStorage() {
            try {
                const lastOnline = localStorage.getItem('locket_last_online');
                const lastActivating = localStorage.getItem('locket_last_activating');
                const lastSuccess = localStorage.getItem('locket_last_success');
                const lastRemaining = localStorage.getItem('locket_last_remaining');

                const onlineEl = document.getElementById('countOnline');
                const activatingEl = document.getElementById('countActivating');
                const successEl = document.getElementById('countTodaySuccess');
                const remainingEl = document.getElementById('countTodayRemaining');

                if (onlineEl) {
                    if (lastOnline !== null && lastOnline !== undefined && lastOnline !== 'null' && lastOnline !== '') {
                        onlineEl.innerText = lastOnline;
                    } else if (onlineEl.innerText === '--' || !onlineEl.innerText) {
                        onlineEl.innerText = '1';
                    }
                }
                if (activatingEl) {
                    if (lastActivating !== null && lastActivating !== undefined && lastActivating !== 'null' && lastActivating !== '') {
                        activatingEl.innerText = lastActivating;
                    } else if (activatingEl.innerText === '--' || !activatingEl.innerText) {
                        activatingEl.innerText = '0';
                    }
                }
                if (successEl && lastSuccess !== null && lastSuccess !== undefined && lastSuccess !== 'null' && lastSuccess !== '') {
                    if (successEl.innerText === '--' || !successEl.innerText) successEl.innerText = lastSuccess;
                }
                if (remainingEl && lastRemaining !== null && lastRemaining !== undefined && lastRemaining !== 'null' && lastRemaining !== '') {
                    if (remainingEl.innerText === '--' || !remainingEl.innerText) remainingEl.innerText = lastRemaining;
                }
            } catch(e) {}
        }

        let realOnlineBase = null;
        let realActivatingBase = null;

        // Hiệu ứng nhảy số mượt (Smooth Counter Animation)
        function animateCounter(elId, targetValue) {
            const el = document.getElementById(elId);
            if (!el || targetValue === undefined || targetValue === null) return;

            const startValue = parseInt(el.innerText) || 0;
            const finalValue = parseInt(targetValue) || 0;

            if (isNaN(startValue) || startValue === finalValue) {
                el.innerText = finalValue;
                return;
            }

            const startTime = performance.now();
            const duration = 800; // 0.8 giây nhảy số mượt

            function step(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const easeProgress = progress * (2 - progress);
                const currentValue = Math.round(startValue + (finalValue - startValue) * easeProgress);
                
                el.innerText = currentValue;

                if (progress < 1) {
                    requestAnimationFrame(step);
                } else {
                    el.innerText = finalValue;
                }
            }
            requestAnimationFrame(step);
        }

        async function syncQueue(retryCount = 0) {
            try {
                const data = await apiCall('queue_sync');
                
                if (data && data.online_count !== undefined) {
                    realOnlineBase = parseInt(data.online_count) || 1;
                    realActivatingBase = data.queue ? (parseInt(data.queue.queue_count) || 0) : 0;

                    animateCounter('countOnline', realOnlineBase);
                    animateCounter('countActivating', realActivatingBase);

                    try {
                        localStorage.setItem('locket_last_online', realOnlineBase);
                        localStorage.setItem('locket_last_activating', realActivatingBase);
                    } catch(e) {}
                }
            } catch (err) {
                console.log('Lỗi sync queue:', err);
                if (retryCount < 2) {
                    setTimeout(() => syncQueue(retryCount + 1), 1000);
                }
            }
        }

        async function syncLimitStatus(retryCount = 0) {
            try {
                const data = await apiCall('limit_status');

                if (data && data.success && data.limit) {
                    updateLimitDisplay(data.limit);
                    try {
                        if (data.limit.today_success_count !== undefined) localStorage.setItem('locket_last_success', data.limit.today_success_count);
                        if (data.limit.today_remaining !== undefined) localStorage.setItem('locket_last_remaining', data.limit.today_remaining);
                    } catch(e) {}
                    return data.limit;
                }
            } catch (err) {
                console.log('Lỗi sync limit:', err);
                if (retryCount < 2) {
                    setTimeout(() => syncLimitStatus(retryCount + 1), 1000);
                }
            }

            return null;
        }

        // Tự động khôi phục dữ liệu ngay khi tải trang
        restoreStatsFromLocalStorage();

        // 1. Tự động dao động nhẹ (Micro-fluctuation) tạo cảm giác LIVE liên tục ở Frontend
        setInterval(() => {
            if (document.visibilityState !== 'visible' || realOnlineBase === null) return;

            if (Math.random() < 0.45) {
                const delta = Math.floor(Math.random() * 3) - 1; // -1, 0, hoặc +1
                const simulatedOnline = Math.max(1, realOnlineBase + delta);
                animateCounter('countOnline', simulatedOnline);
            }
        }, 3000);

        // 2. Đồng bộ số liệu thực từ Server
        syncQueue();
        setInterval(() => {
            if (document.visibilityState === 'visible') syncQueue();
        }, 10000);

        syncLimitStatus();
        setInterval(() => {
            if (document.visibilityState === 'visible') syncLimitStatus();
        }, 15000);

        // Bắt sự kiện khi user quay lại tab / mở lại app browser trên điện thoại
        function handleTabResume() {
            restoreStatsFromLocalStorage();
            if (document.visibilityState === 'visible') {
                syncQueue(0);
                syncLimitStatus(0);
            }
        }

        document.addEventListener('visibilitychange', handleTabResume);
        window.addEventListener('focus', handleTabResume);
        window.addEventListener('pageshow', function(e) {
            restoreStatsFromLocalStorage();
            syncQueue(0);
            syncLimitStatus(0);
        });
        window.addEventListener('online', handleTabResume);

        usernameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') searchUser();
        });

        async function apiCall(action, extra = {}, timeoutMs = 8000) {
            const form = new FormData();
            form.append('action', action);
            form.append('csrf_token', CSRF_TOKEN);
            Object.entries(extra).forEach(([k, v]) => form.append(k, v));

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

            try {
                const res = await fetch(GATEWAY, {
                    method: 'POST',
                    body: form,
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal
                });

                const text = await res.text();
                const json = JSON.parse(text);

                // Nếu backend phát hiện token cũ hết hạn nhưng đã cấp lại CSRF token mới hợp lệ
                if (json && json.new_csrf_token) {
                    CSRF_TOKEN = json.new_csrf_token;
                }

                // Tự động làm mới trang ngay nếu không thể phục hồi
                if (json && json.message && json.message.includes('Phiên làm việc hết hạn')) {
                    console.warn('CSRF Token expired, reloading page for a new token...');
                    location.reload();
                }

                return json;
            } catch (err) {
                if (err.name === 'AbortError') {
                    throw new Error('Yêu cầu hết thời gian chờ (Timeout)');
                }
                throw err;
            } finally {
                clearTimeout(timeoutId);
            }
        }

        function updateLimitDisplay(limitData) {
            if (!limitData) return;

            isNewIp = limitData.is_new_ip ?? isNewIp;
            isFirstOfDay = limitData.is_first_of_day ?? isFirstOfDay;

            if (limitData.is_admin !== undefined) {
                isLimitAdmin = Boolean(limitData.is_admin);
            }

            if (limitData.is_ctv !== undefined) {
                isLimitCtv = Boolean(limitData.is_ctv);
            }

            if (limitData.today_success_count !== undefined) {
                todaySuccessCount = Number(limitData.today_success_count);
            }

            if (limitData.today_remaining !== undefined) {
                todayRemaining = Number(limitData.today_remaining);
            }

            const successEl = document.getElementById('countTodaySuccess');
            const remainingEl = document.getElementById('countTodayRemaining');

            if (!successEl || !remainingEl) return;

            if (isLimitAdmin) {
                successEl.innerText = 'Admin';
                remainingEl.innerText = '∞';
                return;
            }

            if (isLimitCtv) {
                successEl.innerText = 'CTV · ' + todaySuccessCount;
                remainingEl.innerText = todayRemaining;
                return;
            }

            successEl.innerText = todaySuccessCount;
            remainingEl.innerText = todayRemaining;
        }

        async function syncLimitStatus() {
            try {
                const data = await apiCall('limit_status');

                if (data.success && data.limit) {
                    updateLimitDisplay(data.limit);
                    return data.limit;
                }
            } catch (err) {
                console.log('Lỗi sync limit:', err);
            }

            return null;
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '—';
            const d = document.createElement("div");
            d.textContent = String(str);
            return d.innerHTML;
        }

        const clientSearchCache = new Map();
        let lastSearchTime = 0;

        async function searchUser() {
            const q = usernameInput.value.trim();
            if (!q) {
                showError('Nhập username đi nào~');
                return;
            }

            // Anti-spam throttling: Giới hạn bấm tối thiểu cách nhau 1 giây
            const now = Date.now();
            if (now - lastSearchTime < 1000) {
                return;
            }
            lastSearchTime = now;

            // Đọc từ Cache JS nếu đã từng tìm username này trong phiên
            const cacheKey = q.toLowerCase();
            if (clientSearchCache.has(cacheKey)) {
                const cached = clientSearchCache.get(cacheKey);
                if (cached.success && cached.data) {
                    renderUser(cached.data, cached.resolved_username);
                } else {
                    showNotFoundUser();
                }
                return;
            }

            searchBtn.disabled = true;
            resultDiv.innerHTML = `
                <div class="loading-card">
                    <div class="spinner"></div>
                    <span>Đang quét <strong>${escapeHtml(q)}</strong>…</span>
                </div>`;

            try {
                const data = await apiCall('search', { username: q });
                if (data.success && data.data) {
                    clientSearchCache.set(cacheKey, data);
                    renderUser(data.data, data.resolved_username);
                } else {
                    showNotFoundUser();
                }
            } catch (err) {
                showError(err.message || 'Lỗi kết nối');
            } finally {
                searchBtn.disabled = false;
                syncLimitStatus();
            }
        }

        function pickUserFields(raw) {
            if (raw.data) return raw.data;
            if (raw.result) return raw.result;
            return raw;
        }

        function renderActivationGuide() {
            return `
                <div class="activation-guide-card" id="activationGuide">
                    <div class="activation-guide-head">
                        <i class="fa-solid fa-book-open"></i>
                        Hướng dẫn kích hoạt Gold trên Điện thoại
                    </div>

                    <ol class="activation-guide-list">
                        <li>Nhập đúng <strong>username hoặc link Locket</strong>, sau đó bấm nút <strong>Tìm</strong>.</li>
                        <li>Khi hiện thông tin tài khoản, bấm <strong>Kích hoạt Gold</strong>.</li>
                        <li>Giữ nguyên trang đến khi thanh tiến trình báo <strong>100%</strong> và trạng thái đổi sang <strong>Locket Gold</strong>.</li>
                        <li>Sau khi thành công, mở Locket lại để kiểm tra. Nếu chưa thấy Gold, hãy tắt app rồi mở lại vài lần.</li>
                        <li>Sau khi check đã lên Gold ở Locket, vui lòng cài đặt DNS theo hướng dẫn bên dưới vào.</li>
                    </ol>

                    <div class="activation-guide-note">
                        <p><strong><i class="fa-brands fa-apple"></i> iOS:</strong> Bấm vào nút bên dưới <strong>Safari</strong> &rarr; chọn <strong>Cho phép</strong> &rarr; vào <strong>Cài đặt</strong> và nhấn <strong>Cài đặt profile</strong> (nhập passcode nếu có).</p>
                        <a href="${MOBILECONFIG_URL}" class="btn-config iOS-btn" target="_blank" rel="noopener" style="display: inline-block; margin: 10px 0 20px 0; padding: 10px 20px; border: 1px solid #ccc; border-radius: 20px; text-decoration: none; color: #333;">
                            <i class="fa-brands fa-apple"></i> iOS &bull; Config chặn thu hồi Locket
                        </a>
                    </div>

                    <div class="video-guide-zone">
                        <div class="video-title">
                            <i class="fa-solid fa-circle-play"></i> Video hướng dẫn kích hoạt & cài DNS:
                        </div>
                        <div class="video-wrapper">
                            <iframe
                                src="https://www.tiktok.com/embed/v3/7648255968739986695"
                                allowfullscreen
                                loading="lazy">
                            </iframe>
                        </div>
                    </div>

                    <div class="locket-warning-card">
                        <div class="locket-warning-title">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            Lưu ý khi sử dụng Locket username — 3 không
                        </div>

                        <ol class="locket-warning-list">
                            <li>
                                <strong>Không sử dụng VPN</strong> khi truy cập vào Locket, đặc biệt là các cách lên bằng 
                                <strong>Shadowrocket, QuantumultX, Surge,...</strong> vì có thể rớt Gold ngay lập tức.
                            </li>
                            <li>
                                <strong>Hạn chế cài DNS khác</strong> có chứa server khác, vì điều đó có thể làm hệ thống dễ bị xung đột và rớt Gold.
                            </li>
                            <li>
                                <strong>Không được đăng xuất tài khoản Locket</strong>, vì khi đăng xuất thì đồng nghĩa với việc có thể rớt Gold.
                            </li>
                        </ol>
                    </div>

                    <div class="how-it-works-card">
                        <div class="how-it-works-title">
                            <i class="fa-solid fa-shield-heart"></i>
                            Nguyên lý hoạt động & minh bạch trước khi dùng
                        </div>

                        <ol class="how-it-works-list">
                            <li>
                                Khi mở app, Locket thường sẽ kiểm tra trạng thái tài khoản trước rồi mới tải giao diện.
                                Nếu tài khoản đang có Gold thì giao diện Gold sẽ được hiển thị.
                            </li>
                            <li>
                                Trang của mình hoạt động như sau: Khi người dùng nhập và kích hoạt Gold thì nó sẽ kích hoạt trực tiếp lên nick người đó thông qua ID của người dùng.
                                Sau khi kích hoạt trên web thành công, tài khoản đó sẽ hiển thị trạng thái Gold trong app.
                                Tuy nhiên, trạng thái Gold không ổn định vĩnh viễn và có thể thay đổi nếu Locket kiểm tra lại từ phía server.
                            </li>
                            <li>
                                Tức là gói Gold này ban đầu là gói mình đã mua trên tài khoản Locket của mình, rồi mình lấy cái Gold đó nhập cho nick khác.
                                Do đó nếu một username khác kích hoạt Gold trên web của mình thì nó sẽ lấy gói Gold đó nạp qua nick của người đó.
                                Nghĩa là người nào kích hoạt sau sẽ lấy Gold của người trước đó nạp vào. Nói cho vui tai là "Gold sẽ không tự mất đi mà nó sẽ truyền từ nick này sang nick khác" =))
                            </li>
                            <li>
                                Để tránh tình trạng chỉ sài được trên một nick, và sử dụng trên mỗi nick một thời gian ngắn.
                                Thì cái config DNS này sinh ra nhằm để hỗ trợ hạn chế lỗi mất giao diện/tính năng Gold trong quá trình sử dụng (tức là chặn Locket quét).
                                Thực tế là trên hệ thống của Locket thì nick bạn đã mất Gold nhưng do có DNS nên nó vẫn còn duy trì được giao diện.
                                Đây là một phương án hỗ trợ trải nghiệm, không phải tính năng chính thức của Locket.
                            </li>
                        </ol>

                        <div class="how-it-works-note">
                            <strong>Cách hoạt động của config DNS:</strong>
                            config DNS/profile có thể sẽ quét và đi qua hết các request của máy bạn, nhưng nó sẽ chỉ xử lý các phần liên quan đến Locket.
                            <ul class="privacy-mini-list">
                                <li>Không có quyền tự đọc ảnh, video, danh bạ, tin nhắn hay file cá nhân trong máy.</li>
                                <li>Không nên dùng chung với VPN/DNS khác vì có thể xung đột và làm mất trạng thái Gold.</li>
                                <li>Đây là cách dùng không chính thức, nên vẫn có rủi ro mất Gold nếu Locket thay đổi cơ chế kiểm tra.</li>
                                <li>Nếu không đồng ý với cách hoạt động này, bạn không nên cài config DNS này và chấp nhận sài cách lên Gold khác.</li>
                            </ul>
                        </div>
                    </div>
                </div>`;
        }

        document.addEventListener('DOMContentLoaded', function () {
            const publicGuide = document.getElementById('publicActivationGuide');
            if (publicGuide) {
                publicGuide.innerHTML = renderActivationGuide();
            }
        });

        function buildUserGrid(uid, first, last, username, isInviteLink) {
            const userDisplay = isInviteLink ? '<span style="color:#78716C; font-style:italic;">Ẩn theo Link Mời</span>' : '@' + escapeHtml(username);
            const firstDisplay = isInviteLink ? '<span style="color:#78716C; font-style:italic;">Ẩn</span>' : escapeHtml(first);
            const lastDisplay = isInviteLink ? '<span style="color:#78716C; font-style:italic;">Ẩn</span>' : escapeHtml(last);
            return [
                '<div class="info-cell full"><div class="info-key">UID (Firebase)</div><div class="info-val">', escapeHtml(uid), '</div></div>',
                '<div class="info-cell"><div class="info-key">First name</div><div class="info-val">', firstDisplay, '</div></div>',
                '<div class="info-cell"><div class="info-key">Last name</div><div class="info-val">', lastDisplay, '</div></div>',
                '<div class="info-cell full"><div class="info-key">Username</div><div class="info-val">', userDisplay, '</div></div>'
            ].join('');
        }

        async function renderUser(userData, resolvedUsername) {
            const user = pickUserFields(userData);
            const uid = user.uid || user.id || user.user_id;
            if (!uid) {
                showNotFoundUser();
                return;
            }
            currentUid = uid;

            const isInvite = Boolean(user.is_invite_link || resolvedUsername === 'link_invite');
            const username = isInvite ? 'link_invite' : (user.username || user.user_name || resolvedUsername || '—');
            const first = user.first_name || user.firstName || user.given_name || (isInvite ? 'Ẩn' : '—');
            const last = user.last_name || user.lastName || user.family_name || (isInvite ? 'Ẩn' : '—');
            const fullName = isInvite ? 'Tài khoản Locket' : (`${first !== '—' ? first : ''} ${last !== '—' ? last : ''}`.trim() || username);
            const hasGoldBadge = user.badge === 'locket_gold' || user.badge === 'gold' || user.is_gold === true;

            let avatar = user.profile_picture_url || user.avatar || user.profile_pic || user.photo_url || user.picture;
            if (!avatar) {
                avatar = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(fullName) + '&background=808080&color=FFFFFF&size=200';
            }

            const profileSub = isInvite ? 'Trích xuất từ Link Copy Locket' : `locket.cam/${escapeHtml(username)}`;
            const gridHtml = buildUserGrid(uid, first, last, username, isInvite);

            resultDiv.innerHTML = `
                <div class="profile-card">
                    <div class="profile-top">
                        <div class="avatar-wrapper">
                            <img src="${escapeHtml(avatar)}" alt="" class="avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(fullName)}&background=808080&color=FFFFFF'">
                            <div class="avatar-badge" id="avatarGoldBadge" style="display: ${hasGoldBadge ? 'flex' : 'none'};">
                                <i class="fa-solid fa-heart"></i>
                            </div>
                        </div>
                        <div>
                            <div class="profile-name">${escapeHtml(fullName)}</div>
                            <div class="profile-user">${escapeHtml(profileSub)}</div>
                        </div>
                    </div>

                    <div class="info-grid">${gridHtml}</div>

                    <div class="gold-zone" id="goldZone">
                        <div id="goldStatus" class="status-pill load">
                            <div class="spinner"></div> Đang check Gold…
                        </div>
                        <button type="button" class="btn-gold" id="goldBtn" style="display:none" onclick="activateGold('${escapeHtml(username)}')">
                            <i class="fa-solid fa-bolt"></i> Kích hoạt Gold
                        </button>
                        <div id="progressArea"></div>
                        <div id="afterSuccess"></div>
                    </div>
                </div>`;

            await refreshGoldStatus();
        }

        async function refreshGoldStatus() {
            const statusEl = document.getElementById('goldStatus');
            const btn = document.getElementById('goldBtn');
            if (!statusEl || !currentUid) return;

            try {
                const data = await apiCall('check', { uid: currentUid });
                
                if (data.success) {
                    updateLimitDisplay(data);
                }

                if (data.success && data.has_gold) {
                    const exp = data.expires_date ? new Date(data.expires_date).toLocaleString('vi-VN') : null;
                    
                    statusEl.className = 'status-pill-locket-gold';
                    statusEl.innerHTML = `
                        <div class="locket-gold-icon-box"><i class="fa-solid fa-heart"></i></div>
                        <div class="locket-gold-text-box">
                            <div class="locket-gold-title">Locket Gold</div>
                            <div class="locket-gold-subtitle">${exp ? 'Hết hạn vào ' + exp : 'Đã kích hoạt'}</div>
                        </div>
                        <i class="fa-solid fa-chevron-right locket-gold-arrow"></i>
                    `;
                    if (btn) btn.style.display = 'none';
                    
                    showConfigDownload(true, data.purchase_date || data.expires_date);
                } else if (data.success && data.is_expired) {
                    const exp = data.expires_date ? new Date(data.expires_date).toLocaleString('vi-VN') : null;
                    statusEl.className = 'status-pill load'; 
                    statusEl.innerHTML = `<i class="fa-solid fa-clock-rotate-left"></i> Gold đã hết hạn (${exp}) — bấm kích hoạt lại`;
                    if (btn) btn.style.display = 'flex';
                } else if (data.success) {
                    statusEl.className = 'status-pill no';
                    statusEl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Chưa có Gold — bấm kích hoạt bên dưới';
                    if (btn) btn.style.display = 'flex';
                } else {
                    statusEl.className = 'status-pill no';
                    statusEl.innerHTML = escapeHtml(data.message || 'Không check được Gold');
                }
            } catch (e) {
                statusEl.className = 'status-pill no';
                statusEl.innerHTML = 'Lỗi: ' + escapeHtml(e.message);
            }
        }

        const ACTIVATION_STEPS = [
            'Kết nối máy chủ',
            'Xác thực tài khoản Locket',
            'Gửi yêu cầu kích hoạt Gold',
            'Đồng bộ trạng thái subscription',
            'Hoàn tất!'
        ];

        function renderProgressBase() {
            const stepsHtml = ACTIVATION_STEPS.map((s, i) => {
                return `<li id="step-li-${i}">${escapeHtml(s)} <span class="step-timer" id="step-time-${i}"></span></li>`;
            }).join('');

            return `
                <div class="progress-box">
                    <div class="progress-header">
                        <span>Tiến trình kích hoạt</span>
                        <span class="progress-pct" id="progressPctText">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFillBar" style="width: 0%;"></div>
                    </div>
                    <ul class="progress-steps">${stepsHtml}</ul>
                </div>`;
        }

        // 1. Hàm chuyển đổi mượt mà qua lại giữa 2 Tab nội dung popup công khai
        function switchTab(tabName) {
            currentTab = tabName;
            
            document.getElementById('tabBtnFree').classList.remove('active');
            document.getElementById('tabBtnQr').classList.remove('active');
            document.getElementById('tab-free').style.display = 'none';
            document.getElementById('tab-qr').style.display = 'none';
            
            if (tabName === 'free') {
                document.getElementById('tabBtnFree').classList.add('active');
                document.getElementById('tab-free').style.display = 'block';
                document.getElementById('confirmBtn').disabled = !(clickedTiktok && clickedInsta);
            } else {
                document.getElementById('tabBtnQr').classList.add('active');
                document.getElementById('tab-qr').style.display = 'block';
                document.getElementById('confirmBtn').disabled = false;
            }
        }

        // 2. Popup
        async function activateGold(targetUsername = 'duyvinh09') {
            if (!currentUid || !targetUsername || targetUsername === '—') {
                alert('Không lấy được thông tin username để kích hoạt. Vui lòng tìm lại!');
                return;
            }

            const latestLimit = await syncLimitStatus();
            const adminMode = (latestLimit && latestLimit.is_admin) || isLimitAdmin;
            const ctvMode = (latestLimit && latestLimit.is_ctv) || isLimitCtv;

            if (adminMode || ctvMode) {
                const oldModal = document.getElementById('followModal');
                if (oldModal) oldModal.style.display = 'none';

                realActivationFlow();
                return;
            }
            
            const modal = document.getElementById('followModal');
            const tabsNav = document.querySelector('.modal-tabs');
            
            modal.style.display = 'flex';
            document.getElementById('modalStatus').innerHTML = '';

            const cleanUser = targetUsername.replace(/^@/, '');
            const baseQrUrl = 'https://img.vietqr.io/image/970407-99999999924-qr_only.png';
            const infoParam = encodeURIComponent('LocketGold@' + cleanUser);
            const finalQr = `${baseQrUrl}?amount=&addInfo=${infoParam}&accountName=DINH%20DUY%20VINH`;
            document.getElementById('qrImage').src = finalQr;

            if (isNewIp) {
                if (tabsNav) tabsNav.style.display = 'flex'; 
                clickedTiktok = false;
                clickedInsta = false;
                document.querySelector('.btn-follow.tiktok').innerHTML = '<i class="fa-brands fa-tiktok"></i> Theo dõi TikTok';
                document.querySelector('.btn-follow.insta').innerHTML = '<i class="fa-brands fa-instagram"></i> Theo dõi Instagram';
                switchTab('free');
            } else {
                if (tabsNav) tabsNav.style.display = 'none'; 
                switchTab('qr'); 
            }
        }

        // 3. Ghi nhận thao tác khi người dùng nhấp vào nút follow TikTok/Instagram
        function clickedFollow(platform) {
            if (platform === 'tiktok') {
                clickedTiktok = true;
                document.querySelector('.btn-follow.tiktok').innerHTML = '<i class="fa-solid fa-check"></i> Đã mở TikTok';
            } else if (platform === 'insta') {
                clickedInsta = true;
                document.querySelector('.btn-follow.insta').innerHTML = '<i class="fa-solid fa-check"></i> Đã mở Instagram';
            }

            if (currentTab === 'free' && clickedTiktok && clickedInsta) {
                document.getElementById('confirmBtn').disabled = false;
            }
        }

        // 4. Hàm giả lập bộ lọc quét xác thực
        async function verifyAndProceed() {
            const statusDiv = document.getElementById('modalStatus');
            const confirmBtn = document.getElementById('confirmBtn');
            
            confirmBtn.disabled = true;
            statusDiv.style.color = '#D97706';
            if (currentTab === 'free') {
                statusDiv.innerHTML = `<div class="spinner" style="display:inline-block; margin-right:5px; vertical-align:middle;"></div> Đang đồng bộ hóa dữ liệu MXH...`;
            } else {
                statusDiv.innerHTML = `<div class="spinner" style="display:inline-block; margin-right:5px; vertical-align:middle;"></div> Đang quét lệnh giao dịch ngân hàng...`;
            }
            await new Promise(r => setTimeout(r, 400));

            statusDiv.style.color = '#16A34A';
            statusDiv.innerHTML = `<i class="fa-solid fa-circle-check"></i> Xác minh hợp lệ! Đang chạy luồng kích hoạt...`;
            await new Promise(r => setTimeout(r, 300));
            document.getElementById('followModal').style.display = 'none';
            realActivationFlow();
        }

        // 5. Hàm chạy ngầm Core kích hoạt lõi PHP gốc (Hoàn hảo UI Tiến trình)
        async function realActivationFlow() {
            const btn = document.getElementById('goldBtn');
            const progressArea = document.getElementById('progressArea');
            const afterSuccess = document.getElementById('afterSuccess');
            const statusEl = document.getElementById('goldStatus');

            btn.disabled = true;
            afterSuccess.innerHTML = '';
            progressArea.innerHTML = ''; 

            let apiInterval = null;

            try {
                statusEl.className = 'status-pill load';
                statusEl.innerHTML = `<div class="spinner"></div> Đang xin slot server...`;
                
                let queueData = await apiCall('queue_join', {}, 15000);
                syncQueue(); 

                let queueStartTime = Date.now();
                while (queueData && queueData.queue && queueData.queue.status !== 'your_turn') {
                    for (let tick = 0; tick < 2; tick++) {
                        let waitedSec = Math.floor((Date.now() - queueStartTime) / 1000);
                        if (waitedSec > 60) {
                            apiCall('queue_finish');
                            statusEl.className = 'status-pill no';
                            statusEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> Server bận. Vui lòng thử lại sau giây lát!';
                            btn.disabled = false;
                            return;
                        }
                        statusEl.innerHTML = `
                            <div class="queue-timer-box">
                                <div class="q-text">
                                    <div class="spinner"></div> 
                                    <span>Xếp hàng vị trí: <strong>${queueData.queue.position}</strong></span>
                                </div>
                                <div class="q-time"><i class="fa-regular fa-clock"></i> ${waitedSec}s</div>
                            </div>
                        `;
                        await new Promise(r => setTimeout(r, 1000));
                    }
                    
                    queueData = await apiCall('queue_sync', {}, 15000);
                    if (queueData) {
                        animateCounter('countOnline', queueData.online_count || 0);
                        animateCounter('countActivating', queueData.queue ? queueData.queue.queue_count : 0);
                    }
                }

                statusEl.innerHTML = `<div class="spinner"></div> Đang xử lý kích hoạt...`;
                progressArea.innerHTML = renderProgressBase(); 

                // Gửi request API kích hoạt song song với timeout 35 giây (tránh bị fetch Abort 8s)
                const activatePromise = apiCall('activate', { uid: currentUid, sandbox: '1' }, 35000);

                let currentPct = 0;
                const totalSteps = ACTIVATION_STEPS.length;
                for (let i = 0; i < totalSteps - 2; i++) {
                    let stepLi = document.getElementById(`step-li-${i}`);
                    if (stepLi) stepLi.className = 'active';

                    let targetPct = ((i + 1) / totalSteps) * 100;
                    let stepDuration = 200; 
                    let stepStartTime = Date.now();
                    let startPct = currentPct;

                    while(Date.now() - stepStartTime < stepDuration) {
                        let elapsed = Date.now() - stepStartTime;
                        let progressRatio = elapsed / stepDuration;
                        currentPct = startPct + (targetPct - startPct) * progressRatio;

                        let fillEl = document.getElementById('progressFillBar');
                        let pctEl = document.getElementById('progressPctText');
                        let timeEl = document.getElementById(`step-time-${i}`);
                        if (fillEl) fillEl.style.width = currentPct + '%';
                        if (pctEl) pctEl.innerText = Math.floor(currentPct) + '%';
                        if (timeEl) timeEl.innerText = (elapsed / 1000).toFixed(1) + 's';
                        
                        await new Promise(r => requestAnimationFrame(r));
                    }

                    currentPct = targetPct;
                    let fillEl = document.getElementById('progressFillBar');
                    let pctEl = document.getElementById('progressPctText');
                    let timeEl = document.getElementById(`step-time-${i}`);
                    let doneStepLi = document.getElementById(`step-li-${i}`);
                    if (fillEl) fillEl.style.width = currentPct + '%';
                    if (pctEl) pctEl.innerText = Math.floor(currentPct) + '%';
                    if (doneStepLi) doneStepLi.className = 'done';
                    if (timeEl) timeEl.innerHTML = '<i class="fa-solid fa-check"></i>';
                }

                const syncStepIndex = 3;
                let syncStepLi = document.getElementById(`step-li-${syncStepIndex}`);
                if (syncStepLi) syncStepLi.className = 'active';
                
                let apiStartTime = Date.now();
                apiInterval = setInterval(() => {
                    let elapsed = (Date.now() - apiStartTime) / 1000;
                    let syncTimeEl = document.getElementById(`step-time-${syncStepIndex}`);
                    if (syncTimeEl) syncTimeEl.innerText = elapsed.toFixed(1) + 's';
                    
                    if (currentPct < 95) {
                        currentPct += 0.3;
                        let fillEl = document.getElementById('progressFillBar');
                        let pctEl = document.getElementById('progressPctText');
                        if (fillEl) fillEl.style.width = currentPct + '%';
                        if (pctEl) pctEl.innerText = Math.floor(currentPct) + '%';
                    }
                }, 80);

                const data = await activatePromise;
                
                if (apiInterval) {
                    clearInterval(apiInterval);
                    apiInterval = null;
                }

                // Chuyển bước "Đồng bộ" sang Xanh & Tick
                let syncDoneLi = document.getElementById(`step-li-${syncStepIndex}`);
                let syncDoneTime = document.getElementById(`step-time-${syncStepIndex}`);
                if (syncDoneLi) syncDoneLi.className = 'done';
                if (syncDoneTime) syncDoneTime.innerHTML = '<i class="fa-solid fa-check"></i>';

                const finalStepIndex = totalSteps - 1;
                let finalStepLi = document.getElementById(`step-li-${finalStepIndex}`);
                let finalStepTime = document.getElementById(`step-time-${finalStepIndex}`);
                if (finalStepLi) finalStepLi.className = 'done';
                if (finalStepTime) finalStepTime.innerHTML = '<i class="fa-solid fa-check"></i>';
                
                let fillEl = document.getElementById('progressFillBar');
                let pctEl = document.getElementById('progressPctText');
                if (fillEl) fillEl.style.width = '100%';
                if (pctEl) pctEl.innerText = '100%';

                if (data && data.success) {
                    updateLimitDisplay(data);

                    const activatedAt = data.activated_at ? new Date(data.activated_at) : new Date();
                    const exp = data.expires_date ? new Date(data.expires_date).toLocaleString('vi-VN') : null;

                    statusEl.className = 'status-pill-locket-gold';
                    statusEl.innerHTML = `
                        <div class="locket-gold-icon-box"><i class="fa-solid fa-heart"></i></div>
                        <div class="locket-gold-text-box">
                            <div class="locket-gold-title">Locket Gold</div>
                            <div class="locket-gold-subtitle">${exp ? 'Hết hạn vào ' + exp : 'Đã kích hoạt'}</div>
                        </div>
                        <i class="fa-solid fa-chevron-right locket-gold-arrow"></i>
                    `;

                    afterSuccess.innerHTML = `
                        <div class="success-block">
                            <h4><i class="fa-solid fa-champagne-glasses"></i> Thành công rồi nè</h4>
                            <div class="date-row">
                                <span>Ngày kích hoạt Gold</span>
                                <strong>${activatedAt.toLocaleString('vi-VN')}</strong>
                            </div>
                        </div>`;

                    btn.style.display = 'none';
                    showConfigDownload(false);
                } else {
                    progressArea.innerHTML = '';
                    statusEl.className = 'status-pill no';
                    statusEl.innerHTML = escapeHtml((data && data.message) || 'Kích hoạt thất bại');
                    btn.disabled = false;
                }
            } catch (e) {
                if (apiInterval) {
                    clearInterval(apiInterval);
                    apiInterval = null;
                }
                progressArea.innerHTML = '';
                statusEl.className = 'status-pill no';
                statusEl.innerHTML = 'Lỗi hệ thống: ' + escapeHtml(e.message);
                btn.disabled = false;
            } finally {
                if (apiInterval) {
                    clearInterval(apiInterval);
                    apiInterval = null;
                }
                apiCall('queue_finish').catch(() => {});
                syncQueue();
            }
        }

        function formatLocketDate(dateValue) {
            if (!dateValue) return '';

            const date = new Date(dateValue);
            if (isNaN(date.getTime())) {
                return escapeHtml(String(dateValue));
            }

            return date.toLocaleString('vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function showConfigDownload(alreadyHadGold, dateHint) {
            const zone = document.getElementById('goldZone');
            if (!zone || document.getElementById('configBlock')) return;

            const formattedDate = formatLocketDate(dateHint);

            const block = document.createElement("div");
            block.id = 'configBlock';
            block.innerHTML = `
                <p class="config-note">
                    ${alreadyHadGold ? 'Tài khoản này hiện đã có Gold.' : 'Kích hoạt Gold thành công.'}
                    ${formattedDate ? '<br><small>Thời gian: ' + formattedDate + '</small>' : ''}
                </p>
            `;
            zone.appendChild(block);
        }

        function showNotFoundUser() {
            resultDiv.innerHTML = `
                <div class="error-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>
                        Mình tìm nãy giờ cũng không thấy tài khoản này trên Locket.<br>
                        Bạn thử kiểm tra lại @username, bỏ dấu cách, dán link locket.cam hoặc link copy locket.camera/links/... nha.
                    </span>
                </div>
            `;
        }

        function showError(message) {
            resultDiv.innerHTML = `<div class="error-box"><i class="fa-solid fa-triangle-exclamation"></i><span>${escapeHtml(message)}</span></div>`;
        }

        window.addEventListener('load', function () {
            const preloader = document.getElementById('pagePreloader');

            if (!preloader) return;

            setTimeout(function () {
                preloader.classList.add('hide');

                setTimeout(function () {
                    preloader.remove();
                }, 500);
            }, 1000);
        });

        function acceptTermsNotice() {
            localStorage.setItem('locket_terms_accepted', '1');
            const modal = document.getElementById('termsModal');
            if (modal) modal.style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const termsModal = document.getElementById('termsModal');
            const accepted = localStorage.getItem('locket_terms_accepted');

            if (termsModal) {
                termsModal.style.display = accepted === '1' ? 'none' : 'flex';
            }
        });

    </script>
    <div id="followModal" class="modal-overlay" style="display: none;">
        <div class="modal-box">
            <div class="modal-heart"><i class="fa-solid fa-heart"></i></div>
            <h2>Mở khóa Locket Gold 💛</h2>
            
            <div class="modal-tabs">
                <button type="button" class="tab-btn active" id="tabBtnFree" onclick="switchTab('free')">Nhiệm vụ Free</button>
                <button type="button" class="tab-btn" id="tabBtnQr" onclick="switchTab('qr')">Mở khóa nhanh (QR)</button>
            </div>

            <div id="tab-free" class="tab-content">
                <p>Theo dõi các kênh truyền thông của tụi mình để mở khóa hệ thống kích hoạt Gold hoàn toàn miễn phí nha~</p>
                <div class="follow-buttons">
                    <a href="https://www.tiktok.com/@duyvinh.09" target="_blank" class="btn-follow tiktok" onclick="clickedFollow('tiktok')">
                        <i class="fa-brands fa-tiktok"></i> Theo dõi TikTok
                    </a>
                    <a href="https://www.instagram.com/dvinh.09" target="_blank" class="btn-follow insta" onclick="clickedFollow('insta')">
                        <i class="fa-brands fa-instagram"></i> Theo dõi Instagram
                    </a>
                </div>
            </div>

            <div id="tab-qr" class="tab-content" style="display: none;">
                <p>Quét mã ủng hộ duy trì hệ thống (Số tiền tùy tâm) để bỏ qua bước làm nhiệm vụ và mở khóa đặc quyền lập tức ⚡️</p>
                <div class="qr-container">
                    <img id="qrImage" src="" alt="VietQR Locket Gold">
                </div>
            </div>

            <button type="button" class="btn-confirm" id="confirmBtn" disabled onclick="verifyAndProceed()">
                <i class="fa-solid fa-circle-check"></i> Xác nhận hoàn thành
            </button>
            
            <div id="modalStatus" style="margin-top: 12px; font-size: 13px; font-weight: 600; min-height: 20px;"></div>
        </div>
    </div>
    <div id="termsModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-heart">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>

            <h2>Vui lòng đọc kỹ trước khi sử dụng</h2>

            <p style="font-size:13px; color:#78716C; line-height:1.65; text-align:left;">
                Trước khi sử dụng website, bạn cần đọc kỹ phần <strong>hướng dẫn</strong>, 
                <strong>điều khoản sử dụng</strong>, <strong>lưu ý</strong> và 
                <strong>nguyên lý hoạt động</strong>.
                <br><br>
                Việc sử dụng tính năng kích hoạt Locket Gold là hoàn toàn do bạn tự quyết định. 
                Website này không phải tính năng chính thức của Locket và có thể tồn tại rủi ro 
                như mất Gold, lỗi tài khoản, bị hạn chế hoặc bị khóa tài khoản.
                <br><br>
                Khi tiếp tục sử dụng, bạn xác nhận rằng bạn đã hiểu rõ các rủi ro và cam kết 
                tự chịu trách nhiệm cho mọi hành động của mình. Chủ website sẽ 
                <strong>không chịu trách nhiệm dưới mọi hình thức</strong> đối với bất kỳ vấn đề,
                thiệt hại hoặc hậu quả nào phát sinh trong quá trình sử dụng.
            </p>

            <button type="button" class="btn-confirm" onclick="acceptTermsNotice()">
                <i class="fa-solid fa-circle-check"></i> Tôi đã đọc và đồng ý
            </button>
        </div>
    </div>

</body>
</html>
