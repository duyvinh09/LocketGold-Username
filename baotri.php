<?php
// maintenance.php
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Locket Gold · Đang bảo trì</title>
    <meta name="description" content="Locket Gold đang tạm bảo trì hệ thống.">
    <link rel="icon" type="image/png" href="./assets/logoLocket.png"/>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Syne:wght@700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --yellow: #F5C518;
            --yellow-soft: #FFFCF7;
            --yellow-mid: #FFF8EB;
            --yellow-deep: #E8A317;
            --amber: #B45309;
            --card: rgba(255, 255, 255, 0.88);
            --border: rgba(232, 163, 23, 0.24);
            --text: #1C1917;
            --text-soft: #78716C;
            --green: #16A34A;
            --red: #DC2626;
            --shadow: 0 18px 58px rgba(28, 25, 23, 0.08), 0 4px 14px rgba(232, 163, 23, 0.1);
        }

        @font-face {
            font-family: 'ProximaSoft';
            src: url('./assets/proxima_soft_bold.otf') format('opentype');
            font-weight: 700;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'ProximaSoft', 'Outfit', system-ui, sans-serif;
            color: var(--text);
            background: #F7F4EF;
            overflow-x: hidden;
        }

        .bg-scene {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
            pointer-events: none;
            background:
                linear-gradient(165deg, #FFFCFA 0%, #FAF7F2 40%, #F3EDE4 100%);
        }

        .bg-scene::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse 90% 55% at 8% -5%, rgba(255, 200, 120, 0.30) 0%, transparent 58%),
                radial-gradient(ellipse 70% 50% at 105% 15%, rgba(255, 228, 181, 0.34) 0%, transparent 52%),
                radial-gradient(ellipse 55% 45% at 50% 110%, rgba(253, 230, 138, 0.20) 0%, transparent 55%);
        }

        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(50px);
            animation: drift 18s ease-in-out infinite;
        }

        .orb-1 {
            width: min(430px, 90vw);
            height: min(430px, 90vw);
            top: -14%;
            right: -18%;
            background: rgba(255, 183, 77, 0.24);
        }

        .orb-2 {
            width: min(350px, 80vw);
            height: min(350px, 80vw);
            bottom: -12%;
            left: -22%;
            background: rgba(255, 214, 153, 0.22);
            animation-delay: -6s;
        }

        .orb-3 {
            width: 210px;
            height: 210px;
            top: 45%;
            left: 55%;
            background: rgba(255, 248, 235, 0.9);
            filter: blur(14px);
            opacity: 0.75;
            animation-delay: -11s;
        }

        @keyframes drift {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }

            33% {
                transform: translate(-14px, 9px) scale(1.04);
            }

            66% {
                transform: translate(12px, -8px) scale(0.98);
            }
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

        .maintenance-card {
            width: 100%;
            max-width: 440px;
            padding: 26px 20px 22px;
            border-radius: 30px;
            background: var(--card);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            backdrop-filter: blur(16px);
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: cardPop 0.5s ease-out;
        }

        .maintenance-card::before {
            content: '';
            position: absolute;
            width: 190px;
            height: 190px;
            top: -95px;
            right: -80px;
            border-radius: 50%;
            background: rgba(245, 197, 24, 0.17);
        }

        .maintenance-card::after {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            bottom: -85px;
            left: -70px;
            border-radius: 50%;
            background: rgba(255, 183, 77, 0.14);
        }

        @keyframes cardPop {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.96);
            }

            to {
                opacity: 1;
                transform: none;
            }
        }

        .inner {
            position: relative;
            z-index: 2;
        }

        .logo-wrap {
            width: 88px;
            height: 88px;
            margin: 0 auto 18px;
            position: relative;
            display: grid;
            place-items: center;
        }

        .logo-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 2px solid rgba(245, 197, 24, 0.25);
            border-top-color: var(--yellow-deep);
            animation: spin 1.2s linear infinite;
        }

        .logo-icon {
            width: 66px;
            height: 66px;
            border-radius: 22px;
            background: linear-gradient(145deg, #FFE566 0%, var(--yellow) 52%, var(--yellow-deep) 100%);
            display: grid;
            place-items: center;
            color: #7C2D12;
            font-size: 28px;
            box-shadow: 0 12px 34px rgba(232, 163, 23, 0.34);
            animation: heartBeat 1.4s ease-in-out infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes heartBeat {
            0%, 100% {
                transform: scale(1);
            }

            45% {
                transform: scale(1.08);
            }
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: #FFFBEB;
            border: 1px solid rgba(232, 163, 23, 0.22);
            color: var(--amber);
            font-size: 12px;
            font-weight: 800;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 12px;
        }

        .brand .locket {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.45rem, 11vw, 3.4rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: -0.04em;
            color: var(--text);
        }

        .brand .gold {
            font-family: 'Syne', sans-serif;
            font-size: clamp(2.45rem, 11vw, 3.4rem);
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
            background: linear-gradient(120deg, #D97706 0%, #F59E0B 40%, #FBBF24 70%, #E8A317 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 10px rgba(245, 158, 11, 0.22));
        }

        .title {
            margin-top: 8px;
            font-size: 21px;
            font-weight: 900;
            color: var(--text);
        }

        .desc {
            max-width: 340px;
            margin: 10px auto 18px;
            font-size: 14px;
            line-height: 1.65;
            color: var(--text-soft);
        }

        .notice-box {
            text-align: left;
            padding: 14px;
            border-radius: 18px;
            background: #FFFBEB;
            border: 1px solid rgba(232, 163, 23, 0.2);
            margin-bottom: 14px;
        }

        .notice-title {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #92400E;
            font-size: 14px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .notice-box ul {
            margin: 0;
            padding-left: 18px;
        }

        .notice-box li {
            font-size: 12.8px;
            line-height: 1.55;
            color: #57534E;
            margin: 5px 0;
        }

        .status-row {
            display: flex;
            justify-content: center;
            gap: 9px;
            flex-wrap: wrap;
            margin: 16px 0;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.76);
            border: 1px solid rgba(232, 163, 23, 0.2);
            color: #92400E;
            font-size: 12px;
            font-weight: 800;
        }

        .dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--yellow-deep);
            animation: pulse 1.4s ease-in-out infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(232, 163, 23, 0.45);
            }

            70% {
                box-shadow: 0 0 0 8px rgba(232, 163, 23, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(232, 163, 23, 0);
            }
        }

        .progress {
            width: 100%;
            height: 9px;
            border-radius: 999px;
            overflow: hidden;
            background: rgba(232, 163, 23, 0.14);
            border: 1px solid rgba(232, 163, 23, 0.15);
            margin: 14px 0 16px;
        }

        .progress-fill {
            height: 100%;
            width: 65%;
            border-radius: inherit;
            background: linear-gradient(90deg, #FFE566, #F5C518, #E8A317);
            box-shadow: 0 0 18px rgba(245, 197, 24, 0.55);
            animation: loadingMove 1.5s ease-in-out infinite alternate;
        }

        @keyframes loadingMove {
            from {
                width: 42%;
            }

            to {
                width: 86%;
            }
        }

        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 14px;
        }

        .btn {
            width: 100%;
            padding: 13px;
            border-radius: 16px;
            border: none;
            font-family: inherit;
            font-size: 14px;
            font-weight: 900;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: transform 0.15s, box-shadow 0.15s, background 0.2s;
        }

        .btn-primary {
            color: #1F2937;
            background: linear-gradient(135deg, #FFE259 0%, var(--yellow) 50%, var(--yellow-deep) 100%);
            box-shadow: 0 8px 26px rgba(245, 158, 11, 0.30);
        }

        .btn-secondary {
            color: #92400E;
            background: #FFFFFF;
            border: 1.5px solid rgba(232, 163, 23, 0.26);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .footer {
            margin-top: 16px;
            font-size: 12px;
            color: var(--text-soft);
        }

        @media (max-width: 420px) {
            .maintenance-card {
                padding: 24px 16px 20px;
                border-radius: 26px;
            }

            .title {
                font-size: 19px;
            }

            .desc {
                font-size: 13.5px;
            }
        }
    </style>
</head>
<body>
    <div class="bg-scene" aria-hidden="true">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>

    <main class="page">
        <section class="maintenance-card">
            <div class="inner">
                <div class="logo-wrap">
                    <div class="logo-ring"></div>
                    <div class="logo-icon">
                        <i class="fa-solid fa-heart"></i>
                    </div>
                </div>

                <div class="badge">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    Hệ thống đang được nâng cấp
                </div>

                <div class="brand">
                    <span class="locket">Locket</span>
                    <span class="gold">Gold</span>
                </div>

                <h1 class="title">Trang đang tạm bảo trì</h1>

                <p class="desc">
                    Mình đang kiểm tra và nâng cấp lại hệ thống để việc kích hoạt Gold ổn định hơn.
                    Vui lòng quay lại sau nha, cảm ơn mọi người đã thông cảm 💛
                </p>

                <div class="notice-box">
                    <div class="notice-title">
                        <i class="fa-solid fa-circle-info"></i>
                        Lý do bảo trì
                    </div>
                    <ul>
                        <li>Kiểm tra lại hệ thống kích hoạt để hạn chế lỗi trong quá trình sử dụng.</li>
                        <li>Cập nhật hướng dẫn, lưu ý và cơ chế hoạt động rõ ràng hơn.</li>
                        <li>Tối ưu giao diện để mọi người dùng dễ hiểu và thao tác đúng hơn.</li>
                    </ul>
                </div>

                <div class="status-row">
                    <div class="status-pill">
                        <span class="dot"></span>
                        Đang xử lý
                    </div>
                    <div class="status-pill">
                        <i class="fa-solid fa-shield-heart"></i>
                        Locket Gold
                    </div>
                </div>

                <div class="progress">
                    <div class="progress-fill"></div>
                </div>

                <div class="actions">
                    <a class="btn btn-primary" href="javascript:location.reload();">
                        <i class="fa-solid fa-rotate-right"></i>
                        Tải lại trang
                    </a>

                    <a class="btn btn-secondary" href="https://www.tiktok.com/@duyvinh.09" target="_blank" rel="noopener">
                        <i class="fa-brands fa-tiktok"></i>
                        Theo dõi cập nhật
                    </a>
                </div>

                <p class="footer">
                    © 2026 Locket Gold · duyvinh09
                </p>
            </div>
        </section>
    </main>
</body>
</html>