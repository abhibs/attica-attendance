<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Gold Company - Call Centre Script Slider</title>
    <style>
        :root {
            --navy: #071d48;
            --navy-soft: #0d3476;
            --blue: #0c63a6;
            --green: #0d8731;
            --green-soft: #eefbf2;
            --purple: #6d229f;
            --purple-soft: #faf3ff;
            --orange: #f07a18;
            --orange-soft: #fff8ef;
            --red: #d92929;
            --red-soft: #fff4f4;
            --cyan: #0799c9;
            --cyan-soft: #f2fbff;
            --ink: #142035;
            --muted: #526174;
            --line: #dce6ef;
            --paper: #ffffff;
            --page: #eef3f9;
            --content-font-size: 42px;
            --heading-font-size: 52px;
            --kicker-font-size: 20px;
            --slide-transition-duration: 5s;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(12, 99, 166, .14), transparent 30rem),
                linear-gradient(180deg, var(--page), #ffffff 45rem);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.35;
            overflow-x: hidden;
        }

        button {
            font: inherit;
        }

        .page {
            width: 100%;
            height: 100vh;
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr);
            padding: 0;
        }

        .topbar {
            display: grid;
            grid-template-columns: 190px minmax(0, 1fr) 190px;
            align-items: center;
            gap: 18px;
            min-height: 104px;
            padding: 14px 22px;
            background: var(--navy);
            color: #ffffff;
            border-radius: 0;
            overflow: hidden;
        }

        .title {
            margin: 0;
            width: 100%;
            min-width: 0;
            text-align: center;
            font-size: clamp(1.5rem, 3.4vw, 2.9rem);
            font-weight: 900;
            letter-spacing: 0;
            line-height: 1.12;
            overflow-wrap: anywhere;
        }

        .brand {
            display: grid;
            grid-template-columns: 56px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .brand:last-child {
            justify-self: end;
        }

        .brand-logo {
            width: 56px;
            height: 56px;
            border: 2px solid #dcae44;
            border-radius: 50%;
            display: grid;
            place-items: center;
            color: #dcae44;
        }

        .brand-logo svg {
            fill: none;
            stroke: currentColor;
            stroke-width: 2.8;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .brand-name {
            display: grid;
            gap: 0;
            min-width: 0;
            line-height: 1;
            text-transform: uppercase;
        }

        .brand-name strong {
            font-size: 1.62rem;
            font-weight: 900;
            letter-spacing: .02em;
        }

        .brand-name span {
            font-size: .82rem;
            letter-spacing: .04em;
        }

        .slider-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0;
            margin-top: 0;
            min-width: 0;
            min-height: 0;
            height: 100%;
        }

        .slider-main {
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 0;
            min-width: 0;
            min-height: 0;
            height: 100%;
        }

        .slider-toolbar,
        .slider-footer,
        .slide-menu {
            background: rgba(255, 255, 255, .94);
            border: 1px solid var(--line);
            border-radius: 0;
            box-shadow: 0 10px 24px rgba(7, 29, 72, .06);
        }

        .slider-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 12px 14px;
        }

        .slide-count {
            display: flex;
            align-items: baseline;
            gap: 8px;
            color: var(--navy-soft);
            font-weight: 900;
        }

        .slide-count strong {
            font-size: 2rem;
        }

        .slide-count span {
            font-size: 1.16rem;
            color: var(--muted);
        }

        .progress-track {
            flex: 1;
            height: 10px;
            min-width: 100px;
            overflow: hidden;
            border-radius: 999px;
            background: #e9f1f8;
        }

        .progress-bar {
            width: 0;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--green), var(--blue), var(--purple));
            transition: width .24s ease;
        }

        .slide-stage {
            position: relative;
            display: grid;
            min-height: 0;
            min-width: 0;
            height: 100%;
            overflow: hidden;
        }

        .slide-track {
            display: flex;
            width: 100%;
            height: 100%;
            transform: translateX(-100%);
            transition: transform var(--slide-transition-duration) ease-in-out;
            will-change: transform;
        }

        .slide {
            display: grid;
            grid-template-rows: auto auto;
            align-content: center;
            justify-items: center;
            gap: 38px;
            flex: 0 0 100%;
            min-width: 0;
            height: 100%;
            padding: 42px;
            background: var(--paper);
            border: 2px solid var(--blue);
            border-radius: 0;
            box-shadow: 0 14px 32px rgba(7, 29, 72, .09);
            overflow: auto;
        }

        .slide.is-active {
            z-index: 1;
        }

        .slide[data-theme="green"] {
            border-color: rgba(13, 135, 49, .56);
            background: var(--green-soft);
        }

        .slide[data-theme="purple"] {
            border-color: rgba(109, 34, 159, .52);
            background: var(--purple-soft);
        }

        .slide[data-theme="orange"] {
            border-color: #e4a04c;
            background: var(--orange-soft);
        }

        .slide[data-theme="red"] {
            border-color: rgba(217, 41, 41, .42);
            background: var(--red-soft);
        }

        .slide[data-theme="cyan"] {
            border-color: rgba(7, 153, 201, .44);
            background: var(--cyan-soft);
        }

        .slide-header {
            display: grid;
            grid-template-columns: 1fr;
            align-items: center;
            justify-items: center;
            gap: 28px;
            text-align: center;
            width: min(100%, 1500px);
            min-width: 0;
        }

        .step-icon {
            width: 120px;
            height: 120px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #ffffff;
            background: var(--blue);
            font-size: 2.4rem;
            font-weight: 900;
            box-shadow: inset 0 -4px 0 rgba(0, 0, 0, .12);
        }

        .slide[data-theme="green"] .step-icon {
            background: var(--green);
        }

        .slide[data-theme="purple"] .step-icon {
            background: var(--purple);
        }

        .slide[data-theme="orange"] .step-icon {
            background: var(--orange);
        }

        .slide[data-theme="red"] .step-icon {
            background: var(--red);
        }

        .slide[data-theme="cyan"] .step-icon {
            background: var(--cyan);
        }

        .slide-title-wrap {
            min-width: 0;
            text-align: center;
        }

        .slide-kicker {
            display: block;
            margin-bottom: 8px;
            color: var(--muted);
            font-size: var(--kicker-font-size);
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .slide h2 {
            margin: 0;
            color: var(--blue);
            font-size: var(--heading-font-size);
            font-weight: 900;
            line-height: 1.08;
            letter-spacing: 0;
            text-align: center;
            overflow-wrap: anywhere;
            text-transform: uppercase;
        }

        .slide[data-theme="green"] h2 {
            color: var(--green);
        }

        .slide[data-theme="purple"] h2 {
            color: var(--purple);
        }

        .slide[data-theme="orange"] h2 {
            color: var(--orange);
        }

        .slide[data-theme="red"] h2 {
            color: var(--red);
        }

        .slide-body {
            display: grid;
            align-content: start;
            gap: 26px;
            justify-self: center;
            width: min(100%, 1500px);
            min-width: 0;
            padding: 0;
        }

        .slide-body p,
        .slide-body li,
        .option-card,
        .promise-item,
        .legend-row,
        .note-card {
            font-size: var(--content-font-size);
        }

        .slide-body p {
            margin: 0;
            color: #141a23;
            line-height: 1.24;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .slide-body .lead {
            font-weight: 800;
        }

        .slide-body ul {
            margin: 0;
            padding-left: 52px;
            justify-self: center;
            text-align: left;
        }

        .slide-body li {
            margin: 14px 0;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .option-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
        }

        .option-card,
        .note-card {
            display: grid;
            min-width: 0;
            padding: 24px 26px;
            background: #ffffff;
            border: 1.5px solid rgba(7, 29, 72, .14);
            border-radius: 10px;
            color: #141a23;
            font-weight: 800;
            line-height: 1.22;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .option-card {
            grid-template-columns: 62px minmax(0, 1fr);
            align-items: center;
            gap: 18px;
        }

        .option-number {
            width: 62px;
            height: 62px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: var(--orange);
            color: #ffffff;
            font-weight: 900;
        }

        .rate-line,
        .support-number {
            color: var(--green);
            font-size: 56px;
            font-weight: 900;
        }

        .support-number {
            color: var(--red);
        }

        .promise-list,
        .legend-list {
            display: grid;
            gap: 22px;
        }

        .promise-item,
        .legend-row {
            display: grid;
            grid-template-columns: 62px minmax(0, 1fr);
            align-items: center;
            gap: 18px;
            min-width: 0;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .mini-icon {
            width: 62px;
            height: 62px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--blue);
            color: #ffffff;
            font-weight: 900;
        }

        .swatch {
            width: 62px;
            height: 28px;
            border-radius: 5px;
        }

        .swatch.green {
            background: #82c66b;
        }

        .swatch.purple {
            background: #b485d2;
        }

        .swatch.orange {
            background: #ffa53d;
        }

        .swatch.red {
            background: #fa7b7b;
        }

        .swatch.cyan {
            background: #78cce6;
        }

        .slider-footer {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 14px;
            padding: 12px 14px;
        }

        .nav-button {
            min-height: 52px;
            padding: 10px 18px;
            border: 0;
            border-radius: 8px;
            background: var(--navy);
            color: #ffffff;
            font-size: 1.26rem;
            font-weight: 900;
            cursor: pointer;
        }

        .nav-button:hover,
        .nav-button:focus-visible {
            background: var(--navy-soft);
        }

        .nav-button:disabled {
            cursor: not-allowed;
            opacity: .45;
        }

        .next-button {
            justify-self: end;
        }

        .slide-menu {
            display: none;
            align-self: stretch;
            grid-template-rows: auto minmax(0, 1fr);
            gap: 12px;
            padding: 14px;
            min-height: 0;
        }

        .slide-menu h2 {
            margin: 0;
            color: var(--navy);
            font-size: 1.3rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .dot-list {
            display: grid;
            gap: 8px;
            min-height: 0;
            overflow: auto;
            padding-right: 2px;
        }

        .dot-button {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            width: 100%;
            min-height: 42px;
            padding: 8px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: transparent;
            color: var(--muted);
            text-align: left;
            cursor: pointer;
        }

        .dot-button:hover,
        .dot-button:focus-visible,
        .dot-button.is-active {
            border-color: #b8cce0;
            background: #f5f9fd;
            color: var(--navy);
        }

        .dot-index {
            width: 34px;
            height: 28px;
            display: inline-grid;
            place-items: center;
            border-radius: 999px;
            background: #e9f1f8;
            font-size: .82rem;
            font-weight: 900;
        }

        .dot-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 1.02rem;
            font-weight: 800;
        }

        @media (max-width: 1100px) {
            :root {
                --content-font-size: 32px;
                --heading-font-size: 40px;
                --kicker-font-size: 17px;
            }

            .slider-shell {
                grid-template-columns: 1fr;
            }

            .slide-menu {
                align-self: auto;
            }

            .dot-list {
                grid-template-columns: repeat(auto-fit, minmax(70px, 1fr));
                max-height: none;
                overflow: visible;
            }

            .dot-button {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
            }

            .dot-label {
                display: none;
            }
        }

        @media (max-width: 760px) {
            :root {
                --content-font-size: 24px;
                --heading-font-size: 30px;
                --kicker-font-size: 14px;
            }

            .page {
                padding: 0;
            }

            .topbar {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 8px;
                min-height: auto;
                padding: 12px 10px;
            }

            .brand:last-child {
                display: none;
            }

            .title {
                font-size: 1.18rem;
                line-height: 1.18;
            }

            .brand {
                grid-template-columns: 40px minmax(0, 1fr);
            }

            .brand-logo {
                width: 40px;
                height: 40px;
            }

            .brand-name strong {
                font-size: 1.1rem;
            }

            .brand-name span {
                font-size: .58rem;
            }

            .slider-toolbar,
            .slider-footer {
                grid-template-columns: 1fr;
            }

            .slider-toolbar {
                align-items: stretch;
                flex-direction: column;
            }

            .slide-stage {
                min-height: 0;
            }

            .slide {
                padding: 18px 12px;
            }

            .slide.is-active {
                align-content: center;
                gap: 18px;
            }

            .slide-header {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
                gap: 12px;
            }

            .step-icon {
                width: 72px;
                height: 72px;
                font-size: 1.5rem;
            }

            .slide h2 {
                font-size: var(--heading-font-size);
            }

            .slide-body {
                align-content: start;
            }

            .slide-body ul {
                padding-left: 30px;
            }

            .slide-body li {
                margin: 10px 0;
            }

            .option-grid {
                grid-template-columns: 1fr;
            }

            .option-card {
                grid-template-columns: 44px minmax(0, 1fr);
            }

            .option-number {
                width: 44px;
                height: 44px;
            }

            .rate-line,
            .support-number {
                font-size: 30px;
            }

            .slider-footer {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }

            .slider-footer .slide-count {
                grid-column: 1 / -1;
                grid-row: 1;
                justify-self: center;
            }

            .prev-button {
                grid-column: 1;
                grid-row: 2;
                justify-self: stretch;
            }

            .next-button {
                grid-column: 2;
                grid-row: 2;
                justify-self: stretch;
            }

            .nav-button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <main class="page">
        <header class="topbar">
            <div class="brand" aria-label="Attica Gold Company">
                <span class="brand-logo">
                    <svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M24 5 6 42h9l9-24 9 24h9L24 5Z" />
                        <path d="M24 5v31" />
                    </svg>
                </span>
                <span class="brand-name"><strong>Attica</strong><span>Gold Company</span></span>
            </div>
            <h1 class="title">ATTICA GOLD COMPANY - CALL CENTRE SCRIPT SLIDER</h1>
            <div class="brand" aria-label="Attica Gold Company">
                <span class="brand-logo">
                    <svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true">
                        <path d="M24 5 6 42h9l9-24 9 24h9L24 5Z" />
                        <path d="M24 5v31" />
                    </svg>
                </span>
                <span class="brand-name"><strong>Attica</strong><span>Gold Company</span></span>
            </div>
        </header>

        <section class="slider-shell" aria-label="Call centre script slider">
            <div class="slider-main">
                <div class="slider-toolbar">
                    <div class="slide-count" aria-live="polite">
                        <strong><span id="currentSlide">1</span>/<span id="totalSlides">22</span></strong>
                        <span id="currentFlow">Opening</span>
                    </div>
                    <div class="progress-track" aria-hidden="true">
                        <div class="progress-bar" id="progressBar"></div>
                    </div>
                </div>

                <div class="slide-stage" id="slideStage">
                    <div class="slide-track" id="slideTrack">
                        <article class="slide is-active" data-theme="blue" data-flow="Opening">
                            <header class="slide-header">
                                <span class="step-icon">1</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Opening</span>
                                    <h2>Opening Greeting</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Good Morning / Good Afternoon / Good Evening.</p>
                                <p class="lead">Thank you for calling Attica Gold Company.</p>
                                <p>My name is [Agent Name]. How may I assist you today?</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="orange" data-flow="Common">
                            <header class="slide-header">
                                <span class="step-icon">2</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Common Step</span>
                                    <h2>Service Required</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Sir/Madam, may I know what service is required for you today?</p>
                                <div class="option-grid">
                                    <div class="option-card"><span class="option-number">1</span><span>Physical Gold
                                            Sell</span></div>
                                    <div class="option-card"><span class="option-number">2</span><span>Release Gold
                                            &amp; Takeover with Best Live Gold Rate</span></div>
                                </div>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Rate Update">
                            <header class="slide-header">
                                <span class="step-icon">3</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Rate Update</span>
                                    <h2>Today's Live Gold Rate Update</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Today's live gold buying rate (22KT) is</p>
                                <p class="rate-line">&#8377; XXXXX per gram</p>
                                <p>Rate may vary slightly based on:</p>
                                <ul>
                                    <li>Gold purity</li>
                                    <li>Hallmark verification</li>
                                    <li>Stone deductions (if applicable)</li>
                                </ul>
                                <p class="lead">We provide transparent valuation with the best live market rate.</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">4A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Physical Gold Sell</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Customer wants to sell physical gold.</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">5A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Collect Customer Details</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>May I know your name?</li>
                                    <li>Contact number?</li>
                                    <li>Location / Area?</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">6A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Gold Information</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Approx. gold weight?</li>
                                    <li>Jewellery / Coins / Scrap?</li>
                                    <li>Is the jewellery hallmarked?</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">7A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Documents Required</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Aadhaar Card</li>
                                    <li>PAN Card</li>
                                    <li>Gold Bill (if available)</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">7B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Upload Bills If Available</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>If you have gold purchase bills, please upload them on our official WhatsApp. This
                                    helps us in faster valuation.</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">8A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Payment Mode</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>After valuation, how would you like to receive the payment?</p>
                                <ul>
                                    <li>IMPS</li>
                                    <li>NEFT</li>
                                </ul>
                                <p class="lead">(We do not provide cash payment)</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="green" data-flow="Physical Gold Sell">
                            <header class="slide-header">
                                <span class="step-icon">9A</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Physical Gold Sell Flow</span>
                                    <h2>Branch &amp; Visit Planning</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Nearest branch details will be shared.</li>
                                    <li>When are you planning to visit the branch?</li>
                                    <li>Preferred date &amp; time?</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">4B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Release Gold &amp; Takeover</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Customer wants to release pledged gold &amp; takeover with best rate.</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">5B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Requirement Details</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Which bank/finance company?</li>
                                    <li>Approx. loan amount?</li>
                                    <li>Approx. gold weight?</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">6B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Documents Required</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>To book the slot, kindly keep ready:</p>
                                <ul>
                                    <li>Gold Loan Document / Pledge Receipt</li>
                                    <li>Aadhaar Card</li>
                                    <li>PAN Card</li>
                                    <li>Cancelled Cheque</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">6C</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Upload Documents Important</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>Please upload the above documents directly on our official WhatsApp number.</p>
                                <p class="lead">This is mandatory to book your slot.</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">7B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Process Explanation</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Verify loan documents</li>
                                    <li>Evaluate pledged gold</li>
                                    <li>Check live gold valuation</li>
                                    <li>Assist in release &amp; takeover process</li>
                                    <li>Provide best possible live gold rate</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">8B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Payment Mode</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>After successful release &amp; valuation, how would you prefer payment?</p>
                                <ul>
                                    <li>IMPS</li>
                                    <li>NEFT</li>
                                </ul>
                                <p class="lead">(We do not provide cash payment)</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="purple" data-flow="Release Gold &amp; Takeover">
                            <header class="slide-header">
                                <span class="step-icon">9B</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Release Gold &amp; Takeover Flow</span>
                                    <h2>Slot Booking &amp; Visit Planning</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Preferred date &amp; time for slot booking?</li>
                                    <li>Share WhatsApp number</li>
                                    <li>Branch details &amp; slot confirmation will be shared</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="blue" data-flow="Promise">
                            <header class="slide-header">
                                <span class="step-icon">P</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Customer Promise</span>
                                    <h2>Our Promise</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <div class="promise-list">
                                    <div class="promise-item"><span class="mini-icon">1</span><span>Best Live Gold
                                            Rate</span></div>
                                    <div class="promise-item"><span class="mini-icon">2</span><span>Transparent
                                            Valuation</span></div>
                                    <div class="promise-item"><span class="mini-icon">3</span><span>Quick &amp; Easy
                                            Process</span></div>
                                    <div class="promise-item"><span class="mini-icon">4</span><span>Instant Payment
                                            (as per mode)</span></div>
                                    <div class="promise-item"><span class="mini-icon">5</span><span>Customer
                                            Satisfaction</span></div>
                                </div>
                            </div>
                        </article>

                        <article class="slide" data-theme="orange" data-flow="Common">
                            <header class="slide-header">
                                <span class="step-icon">10</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Common Step</span>
                                    <h2>Branch Details Sharing</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Branch Address</li>
                                    <li>Landmarks</li>
                                    <li>Working Hours</li>
                                    <li>Location link will be shared on WhatsApp / SMS</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="red" data-flow="Support">
                            <header class="slide-header">
                                <span class="step-icon">11</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Support</span>
                                    <h2>Customer Support</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <p>If you face any issue, please contact our customer support:</p>
                                <p class="support-number">8880 300 300</p>
                            </div>
                        </article>

                        <article class="slide" data-theme="cyan" data-flow="Closing">
                            <header class="slide-header">
                                <span class="step-icon">12</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Closing</span>
                                    <h2>Closing</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <ul>
                                    <li>Is there anything else I can assist you with?</li>
                                    <li>Thank you for calling Attica Gold Company.</li>
                                    <li>Have a wonderful day!</li>
                                </ul>
                            </div>
                        </article>

                        <article class="slide" data-theme="blue" data-flow="Legend">
                            <header class="slide-header">
                                <span class="step-icon">L</span>
                                <div class="slide-title-wrap">
                                    <span class="slide-kicker">Reference</span>
                                    <h2>Legend &amp; Note</h2>
                                </div>
                            </header>
                            <div class="slide-body">
                                <div class="legend-list">
                                    <div class="legend-row"><span class="swatch green"></span><span>Physical Gold Sell
                                            Flow</span></div>
                                    <div class="legend-row"><span class="swatch purple"></span><span>Release Gold
                                            &amp; Takeover Flow</span></div>
                                    <div class="legend-row"><span class="swatch orange"></span><span>Common / Shared
                                            Steps</span></div>
                                    <div class="legend-row"><span class="swatch red"></span><span>Support</span></div>
                                    <div class="legend-row"><span class="swatch cyan"></span><span>Closure</span>
                                    </div>
                                </div>
                                <div class="note-card">Note: Gold rate is subject to change as per live market. Final
                                    valuation will be done at the branch.</div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="slider-footer">
                    <button type="button" class="nav-button prev-button" id="prevSlide">Previous</button>
                    <span class="slide-count"><strong><span id="footerCurrent">1</span>/<span
                                id="footerTotal">22</span></strong></span>
                    <button type="button" class="nav-button next-button" id="nextSlide">Next</button>
                </div>
            </div>

            <aside class="slide-menu" aria-label="Slide navigation">
                <h2>Sections</h2>
                <div class="dot-list" id="slideDots"></div>
            </aside>
        </section>
    </main>

    <script>
        const slideTrack = document.getElementById('slideTrack');
        const slides = Array.from(slideTrack.querySelectorAll('.slide'));
        const currentSlide = document.getElementById('currentSlide');
        const totalSlides = document.getElementById('totalSlides');
        const footerCurrent = document.getElementById('footerCurrent');
        const footerTotal = document.getElementById('footerTotal');
        const currentFlow = document.getElementById('currentFlow');
        const progressBar = document.getElementById('progressBar');
        const prevButton = document.getElementById('prevSlide');
        const nextButton = document.getElementById('nextSlide');
        const dotsContainer = document.getElementById('slideDots');
        const slideStage = document.getElementById('slideStage');
        const autoSlideDelay = 0;
        const slideMotionTiming = 30000;
        const firstSlideClone = slides[0].cloneNode(true);
        const lastSlideClone = slides[slides.length - 1].cloneNode(true);
        let activeIndex = 1;
        let touchStartX = 0;
        let autoSlideTimer = null;

        document.documentElement.style.setProperty('--slide-transition-duration', (slideMotionTiming / 1000) + 's');

        firstSlideClone.classList.remove('is-active');
        firstSlideClone.classList.add('slide-clone');
        firstSlideClone.setAttribute('aria-hidden', 'true');
        lastSlideClone.classList.remove('is-active');
        lastSlideClone.classList.add('slide-clone');
        lastSlideClone.setAttribute('aria-hidden', 'true');
        slideTrack.insertBefore(lastSlideClone, slides[0]);
        slideTrack.appendChild(firstSlideClone);

        totalSlides.textContent = slides.length;
        footerTotal.textContent = slides.length;

        const dotButtons = slides.map((slide, index) => {
            const title = slide.querySelector('h2').textContent.trim();
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dot-button';
            button.setAttribute('aria-label', 'Go to slide ' + (index + 1) + ': ' + title);
            button.innerHTML = '<span class="dot-index">' + (index + 1) + '</span><span class="dot-label">' +
                title + '</span>';
            button.addEventListener('click', () => goToSlide(index));
            dotsContainer.appendChild(button);
            return button;
        });

        function getRealIndex(trackIndex) {
            if (trackIndex === 0) {
                return slides.length - 1;
            }

            if (trackIndex === slides.length + 1) {
                return 0;
            }

            return trackIndex - 1;
        }

        function updateSlideContent(trackIndex) {
            const realIndex = getRealIndex(trackIndex);

            slides.forEach((slide, slideIndex) => {
                const isActive = slideIndex === realIndex;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
            });

            dotButtons.forEach((button, dotIndex) => {
                const isActive = dotIndex === realIndex;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-current', isActive ? 'true' : 'false');
            });

            const displayIndex = realIndex + 1;
            currentSlide.textContent = displayIndex;
            footerCurrent.textContent = displayIndex;
            currentFlow.textContent = slides[realIndex].dataset.flow || '';
            progressBar.style.width = ((displayIndex / slides.length) * 100) + '%';
            prevButton.textContent = 'Previous';
            nextButton.textContent = 'Next';
        }

        function showSlide(index, useTransition = true) {
            activeIndex = index;
            slideTrack.style.transition = useTransition ? '' : 'none';
            slideTrack.style.transform = 'translateX(-' + (activeIndex * 100) + '%)';
            updateSlideContent(activeIndex);

            if (!useTransition) {
                slideTrack.offsetHeight;
                slideTrack.style.transition = '';
            }
        }

        function startAutoSlide() {
            clearTimeout(autoSlideTimer);

            if (document.hidden) {
                return;
            }

            autoSlideTimer = setTimeout(() => {
                showSlide(activeIndex + 1);
            }, autoSlideDelay);
        }

        function goToSlide(index) {
            clearTimeout(autoSlideTimer);
            showSlide(index + 1);
        }

        slideTrack.addEventListener('transitionend', (event) => {
            if (event.target !== slideTrack || event.propertyName !== 'transform') {
                return;
            }

            if (activeIndex === slides.length + 1) {
                showSlide(1, false);
            }

            if (activeIndex === 0) {
                showSlide(slides.length, false);
            }

            startAutoSlide();
        });

        prevButton.addEventListener('click', () => {
            clearTimeout(autoSlideTimer);
            showSlide(activeIndex - 1);
        });

        nextButton.addEventListener('click', () => {
            clearTimeout(autoSlideTimer);
            showSlide(activeIndex + 1);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowLeft') {
                clearTimeout(autoSlideTimer);
                showSlide(activeIndex - 1);
            }

            if (event.key === 'ArrowRight') {
                clearTimeout(autoSlideTimer);
                showSlide(activeIndex + 1);
            }
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearTimeout(autoSlideTimer);
                return;
            }

            startAutoSlide();
        });

        slideStage.addEventListener('touchstart', (event) => {
            touchStartX = event.changedTouches[0].screenX;
        }, {
            passive: true
        });

        slideStage.addEventListener('touchend', (event) => {
            const touchEndX = event.changedTouches[0].screenX;
            const distance = touchEndX - touchStartX;

            if (Math.abs(distance) > 50) {
                clearTimeout(autoSlideTimer);
                showSlide(activeIndex + (distance < 0 ? 1 : -1));
            }
        }, {
            passive: true
        });

        showSlide(1, false);
        startAutoSlide();
    </script>
</body>

</html>
