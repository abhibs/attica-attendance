<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attica Gold Company - Call Centre Script Flowchart</title>
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
            --ink: #142035;
            --muted: #4d5a6b;
            --line: #dfe8f0;
            --paper: #ffffff;
            --page: #eef3f9;
            --content-font-size: 30px;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            width: 100%;
            min-height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(12, 99, 166, .12), transparent 32rem),
                linear-gradient(180deg, var(--page), #ffffff 42rem);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            line-height: 1.35;
            overflow-x: hidden;
        }

        .poster {
            width: 100%;
            min-height: 100vh;
            margin: 0;
            padding: 16px 24px 24px;
            background: var(--paper);
            border: 1px solid #d7e0eb;
            border-radius: 0;
            box-shadow: none;
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
            border-radius: 10px;
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

        .brand-logo svg,
        .promise .shield svg {
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
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

        .flow {
            position: relative;
            min-width: 0;
            padding-top: 16px;
        }

        .top-flow {
            position: relative;
            z-index: 1;
            display: grid;
            justify-items: center;
            gap: 28px;
            min-width: 0;
        }

        .node {
            position: relative;
            display: grid;
            grid-template-columns: 92px minmax(0, 1fr);
            align-items: center;
            gap: 20px;
            width: 100%;
            padding: 20px 24px;
            background: #ffffff;
            border: 2px solid var(--blue);
            border-radius: 13px;
            box-shadow: 0 8px 20px rgba(7, 29, 72, .06);
            min-width: 0;
            max-width: 100%;
        }

        .node > div,
        .option-pill span:last-child,
        .promise-item span:last-child,
        .legend-row span:last-child {
            width: 100%;
            min-width: 0;
        }

        .node.small-icon {
            grid-template-columns: 78px minmax(0, 1fr);
            gap: 16px;
            padding: 18px 20px;
        }

        .node.compact {
            align-items: start;
        }

        .node.common {
            border-color: #e4a04c;
            background: var(--orange-soft);
        }

        .node.green {
            border-color: rgba(13, 135, 49, .56);
            background: var(--green-soft);
        }

        .node.purple {
            border-color: rgba(109, 34, 159, .52);
            background: var(--purple-soft);
        }

        .node.support {
            border-color: rgba(217, 41, 41, .42);
            background: var(--red-soft);
        }

        .node.close {
            border-color: rgba(7, 153, 201, .44);
            background: #f2fbff;
        }

        .opening {
            max-width: 920px;
        }

        .service {
            max-width: 980px;
        }

        .node h2,
        .node h3 {
            margin: 0 0 6px;
            font-size: var(--content-font-size);
            font-weight: 900;
            line-height: 1.16;
            letter-spacing: .01em;
            text-transform: uppercase;
            overflow-wrap: anywhere;
        }

        .node h2 {
            color: var(--blue);
            text-align: center;
        }

        .node.common h2,
        .node.common h3 {
            color: var(--orange);
        }

        .node.green h3 {
            color: var(--green);
        }

        .node.purple h3 {
            color: var(--purple);
        }

        .node.support h3 {
            color: var(--red);
        }

        .node.close h3 {
            color: var(--blue);
        }

        .node p {
            margin: 0;
            color: #141a23;
            font-size: var(--content-font-size);
            overflow-wrap: anywhere;
        }

        .node .lead {
            margin-bottom: 2px;
            font-weight: 700;
        }

        .node ul {
            margin: 4px 0 0;
            padding-left: 22px;
        }

        .node li {
            margin: 2px 0;
            font-size: var(--content-font-size);
            overflow-wrap: anywhere;
        }

        .icon {
            width: 82px;
            height: 82px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #ffffff;
            background: currentColor;
            flex: 0 0 auto;
        }

        .small-icon .icon {
            width: 70px;
            height: 70px;
        }

        .icon svg {
            width: 48px;
            height: 48px;
            color: #ffffff;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .icon svg .fill {
            fill: currentColor;
            stroke: none;
        }

        .icon.blue {
            color: var(--navy-soft);
        }

        .icon.green {
            color: var(--green);
        }

        .icon.purple {
            color: var(--purple);
        }

        .icon.orange {
            color: var(--orange);
        }

        .icon.red {
            color: var(--red);
        }

        .icon.cyan {
            color: var(--cyan);
        }

        .icon svg {
            color: #ffffff;
        }

        .down::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -28px;
            width: 2px;
            height: 26px;
            background: var(--blue);
            transform: translateX(-50%);
        }

        .down::before {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -31px;
            width: 9px;
            height: 9px;
            border-right: 2px solid var(--blue);
            border-bottom: 2px solid var(--blue);
            transform: translateX(-50%) rotate(45deg);
        }

        .service-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 14px;
        }

        .option-pill {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            min-height: 68px;
            padding: 12px 16px;
            background: #ffffff;
            border: 1.5px solid #e6a34b;
            border-radius: 10px;
            font-weight: 800;
            font-size: var(--content-font-size);
            line-height: 1.18;
            max-width: 100%;
            min-width: 0;
        }

        .option-pill span:last-child {
            display: block;
            overflow-wrap: anywhere;
        }

        .badge-number {
            width: 34px;
            height: 34px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: var(--orange);
            color: #ffffff;
            font-weight: 900;
        }

        .branch-grid {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(250px, .75fr) minmax(0, 1.05fr);
            gap: 30px;
            margin-top: 38px;
            align-items: start;
            min-width: 0;
        }

        .branch {
            display: grid;
            gap: 30px;
            position: relative;
        }

        .branch .node:not(:last-child)::after {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -30px;
            width: 2px;
            height: 30px;
            transform: translateX(-50%);
            background: var(--branch-color);
        }

        .branch .node:not(:last-child)::before {
            content: "";
            position: absolute;
            left: 50%;
            bottom: -33px;
            width: 9px;
            height: 9px;
            border-right: 2px solid var(--branch-color);
            border-bottom: 2px solid var(--branch-color);
            transform: translateX(-50%) rotate(45deg);
        }

        .branch.green-branch {
            --branch-color: var(--green);
        }

        .branch.purple-branch {
            --branch-color: var(--purple);
        }

        .path-marker {
            position: absolute;
            top: -24px;
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #ffffff;
            font-weight: 900;
            font-size: var(--content-font-size);
            z-index: 2;
            box-shadow: 0 3px 0 rgba(7, 29, 72, .16);
        }

        .green-branch .path-marker {
            left: 50%;
            background: var(--green);
            transform: translate(-50%, -50%);
        }

        .purple-branch .path-marker {
            left: 50%;
            background: var(--purple);
            transform: translate(-50%, -50%);
        }

        .center-stack {
            display: grid;
            justify-items: center;
            gap: 96px;
            padding-top: 24px;
        }

        .rate-card {
            width: 100%;
            grid-template-columns: 78px minmax(0, 1fr);
            text-align: center;
        }

        .rate-card .rate-line {
            color: var(--green);
            font-size: var(--content-font-size);
            font-weight: 900;
        }

        .promise {
            position: relative;
            z-index: 2;
            display: block;
            width: 100%;
            max-width: 300px;
            padding: 20px 18px;
            text-align: center;
            border-color: rgba(12, 99, 166, .48);
            background: #f5fbff;
        }

        .promise .shield {
            width: 62px;
            height: 62px;
            margin: 0 auto 8px;
            color: var(--blue);
        }

        .promise h3 {
            color: var(--blue);
            margin-bottom: 10px;
            border-bottom: 1px dashed #aebfd1;
            padding-bottom: 9px;
            font-size: var(--content-font-size);
        }

        .promise-list {
            display: grid;
            gap: 10px;
            text-align: left;
        }

        .promise-item {
            display: grid;
            grid-template-columns: 36px minmax(0, 1fr);
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: var(--content-font-size);
        }

        .mini-icon {
            width: 36px;
            height: 36px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: var(--blue);
            color: #ffffff;
        }

        .mini-icon svg {
            width: 21px;
            height: 21px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2.2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .promise-source {
            position: relative;
        }

        .promise-link {
            --promise-link-width: clamp(28px, 2.4vw, 36px);
            --promise-link-drop: 390px;
            position: absolute;
            top: 50%;
            width: var(--promise-link-width);
            height: var(--promise-link-drop);
            pointer-events: none;
        }

        .promise-source-left .promise-link {
            right: calc(var(--promise-link-width) * -1);
            border-top: 2px dashed rgba(13, 135, 49, .58);
            border-right: 2px dashed rgba(13, 135, 49, .58);
        }

        .promise-source-right .promise-link {
            left: calc(var(--promise-link-width) * -1);
            border-top: 2px dashed rgba(109, 34, 159, .55);
            border-left: 2px dashed rgba(109, 34, 159, .55);
        }

        .promise-link::after {
            content: "";
            position: absolute;
            bottom: 0;
            width: 118px;
            border-bottom: 2px dashed currentColor;
        }

        .promise-source-left .promise-link::after {
            right: -118px;
            color: rgba(13, 135, 49, .58);
        }

        .promise-source-right .promise-link::after {
            left: -118px;
            color: rgba(109, 34, 159, .55);
        }

        .shared-flow {
            position: relative;
            z-index: 1;
            display: grid;
            grid-template-columns: minmax(0, 1fr) 240px;
            gap: 30px;
            margin-top: 30px;
            align-items: end;
        }

        .shared-stack {
            display: grid;
            justify-items: center;
            gap: 30px;
        }

        .shared-stack .node {
            max-width: 860px;
        }

        .support-number {
            color: var(--red);
            font-size: var(--content-font-size);
            font-weight: 900;
        }

        .legend {
            padding: 14px;
            background: #fffdf6;
            border: 1.5px solid #e7bd58;
            border-radius: 10px;
            align-self: stretch;
        }

        .legend h3 {
            margin: 0 0 10px;
            text-align: center;
            font-size: var(--content-font-size);
            text-transform: uppercase;
        }

        .legend-row {
            display: grid;
            grid-template-columns: 28px minmax(0, 1fr);
            align-items: center;
            gap: 8px;
            margin: 8px 0;
            font-size: var(--content-font-size);
            font-weight: 700;
        }

        .swatch {
            width: 28px;
            height: 14px;
            border-radius: 4px;
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

        .note {
            position: relative;
            z-index: 1;
            margin: 14px auto 0;
            width: fit-content;
            max-width: 100%;
            padding: 7px 14px;
            color: var(--navy-soft);
            background: #eff8ff;
            border: 1px solid #9cc7e8;
            border-radius: 8px;
            font-size: var(--content-font-size);
            font-weight: 700;
            text-align: center;
        }

        .desktop-only {
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            overflow: hidden;
        }

        .flow-line {
            position: absolute;
            pointer-events: none;
        }

        .branch-line {
            top: 296px;
            height: 110px;
            border-top: 4px solid var(--line-color);
            border-radius: 12px 12px 0 0;
        }

        .branch-line.left {
            --line-color: var(--green);
            left: 17%;
            width: 22%;
            border-left: 4px solid var(--green);
        }

        .branch-line.right {
            --line-color: var(--purple);
            right: 17%;
            width: 22%;
            border-right: 4px solid var(--purple);
        }

        .branch-line::after {
            content: "";
            position: absolute;
            bottom: -9px;
            width: 14px;
            height: 14px;
            border-right: 4px solid var(--line-color);
            border-bottom: 4px solid var(--line-color);
            transform: rotate(45deg);
        }

        .branch-line.left::after {
            left: -9px;
        }

        .branch-line.right::after {
            right: -9px;
        }

        .connector-rate {
            top: 520px;
            height: 0;
            border-top: 4px solid var(--line-color);
        }

        .connector-rate.left {
            --line-color: var(--green);
            left: 18%;
            width: 19%;
        }

        .connector-rate.right {
            --line-color: var(--purple);
            right: 18%;
            width: 19%;
        }

        .connector-rate::after {
            content: "";
            position: absolute;
            top: -9px;
            width: 14px;
            height: 14px;
            border-top: 4px solid var(--line-color);
            border-right: 4px solid var(--line-color);
        }

        .connector-rate.left::after {
            right: -2px;
            transform: rotate(45deg);
        }

        .connector-rate.right::after {
            left: -2px;
            transform: rotate(-135deg);
        }

        .connector-promise {
            display: none;
        }

        .connector-promise.left {
            --line-color: rgba(13, 135, 49, .45);
        }

        .connector-promise.right {
            --line-color: rgba(109, 34, 159, .42);
        }

        @media (max-width: 960px) {
            .poster {
                padding: 8px 12px 16px;
            }

            .topbar {
                grid-template-columns: 1fr;
                justify-items: center;
                gap: 8px;
            }

            .brand:last-child {
                display: none;
            }

            .branch-grid {
                grid-template-columns: 1fr;
                gap: 26px;
            }

            .center-stack {
                order: -1;
                gap: 20px;
                padding-top: 0;
            }

            .promise-link {
                display: none;
            }

            .path-marker {
                display: none;
            }

            .desktop-only {
                display: none;
            }

            .shared-flow {
                grid-template-columns: 1fr;
            }

            .legend {
                width: 100%;
                max-width: 360px;
                justify-self: center;
            }
        }

        @media (max-width: 620px) {
            .poster {
                width: 100%;
                margin: 0;
                padding: 10px;
                border-radius: 8px;
            }

            .topbar {
                padding: 12px 10px;
                min-height: auto;
            }

            .title {
                font-size: 1.14rem;
                line-height: 1.18;
            }

            .brand {
                grid-template-columns: 38px minmax(0, 1fr);
            }

            .brand-logo {
                width: 38px;
                height: 38px;
            }

            .brand-name strong {
                font-size: 1.08rem;
            }

            .brand-name span {
                font-size: .56rem;
            }

            .node,
            .node.small-icon,
            .rate-card {
                grid-template-columns: 1fr;
                justify-items: center;
                text-align: center;
                padding: 18px 12px;
            }

            .node h2,
            .node h3 {
                font-size: var(--content-font-size);
            }

            .node p {
                font-size: var(--content-font-size);
            }

            .node li {
                font-size: var(--content-font-size);
            }

            .node ul {
                width: 100%;
                text-align: left;
            }

            .service-options {
                grid-template-columns: 1fr;
            }

            .option-pill {
                font-size: var(--content-font-size);
                padding: 11px 12px;
            }

            .shared-stack .node {
                max-width: 100%;
            }
        }
    </style>
</head>

<body>
    <svg width="0" height="0" aria-hidden="true" focusable="false" style="position:absolute">
        <symbol id="logo-mark" viewBox="0 0 48 48">
            <path d="M24 5 6 42h9l9-24 9 24h9L24 5Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round" />
            <path d="M24 5v31" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </symbol>
        <symbol id="icon-headset" viewBox="0 0 48 48">
            <path d="M10 27v-6a14 14 0 0 1 28 0v6" />
            <path d="M10 27h6v12h-6a4 4 0 0 1-4-4v-4a4 4 0 0 1 4-4Z" />
            <path d="M38 27h-6v12h6a4 4 0 0 0 4-4v-4a4 4 0 0 0-4-4Z" />
            <path d="M31 39c-2 3-6 4-11 3" />
        </symbol>
        <symbol id="icon-help" viewBox="0 0 48 48">
            <path d="M12 38c3-7 21-7 24 0" />
            <circle cx="24" cy="17" r="7" />
            <path d="M34 10h4a6 6 0 0 1 0 12h-2l-5 5v-5" />
            <path d="M38 13v.1M38 18v.1" />
        </symbol>
        <symbol id="icon-gold" viewBox="0 0 48 48">
            <path d="M13 35h22l-4-12H17l-4 12Z" />
            <path d="M19 23h10l-3-10h-4l-3 10Z" />
            <path d="M8 35h32" />
            <path d="M24 13v22" />
        </symbol>
        <symbol id="icon-scale" viewBox="0 0 48 48">
            <path d="M24 9v30" />
            <path d="M12 16h24" />
            <path d="M16 16 9 30h14L16 16Z" />
            <path d="M32 16 25 30h14L32 16Z" />
            <path d="M16 39h16" />
        </symbol>
        <symbol id="icon-clipboard" viewBox="0 0 48 48">
            <path d="M15 10h18v6H15z" />
            <path d="M13 13H9v29h30V13h-4" />
            <path d="M16 25h16M16 32h11" />
        </symbol>
        <symbol id="icon-necklace" viewBox="0 0 48 48">
            <path d="M12 12c1 15 7 23 12 23s11-8 12-23" />
            <circle cx="13" cy="29" r="3" />
            <circle cx="24" cy="36" r="3" />
            <circle cx="35" cy="29" r="3" />
        </symbol>
        <symbol id="icon-document" viewBox="0 0 48 48">
            <path d="M14 6h15l7 7v29H14z" />
            <path d="M29 6v8h7" />
            <path d="M19 23h14M19 30h14" />
        </symbol>
        <symbol id="icon-upload" viewBox="0 0 48 48">
            <path d="M17 34h-3a8 8 0 0 1 0-16 11 11 0 0 1 21-2 9 9 0 0 1-1 18h-3" />
            <path d="M24 38V22" />
            <path d="m17 29 7-7 7 7" />
        </symbol>
        <symbol id="icon-rupee" viewBox="0 0 48 48">
            <path d="M16 10h18M16 18h18M16 10h9c9 0 9 14 0 14h-9l16 16" />
        </symbol>
        <symbol id="icon-location" viewBox="0 0 48 48">
            <path d="M24 43s14-12 14-24a14 14 0 0 0-28 0c0 12 14 24 14 24Z" />
            <circle cx="24" cy="19" r="5" />
        </symbol>
        <symbol id="icon-bank" viewBox="0 0 48 48">
            <path d="M7 18h34L24 8 7 18Z" />
            <path d="M11 21v15M20 21v15M28 21v15M37 21v15" />
            <path d="M8 39h32" />
        </symbol>
        <symbol id="icon-gears" viewBox="0 0 48 48">
            <circle cx="18" cy="20" r="5" />
            <path d="M18 8v4M18 28v4M6 20h4M26 20h4M10 12l3 3M23 25l3 3M26 12l-3 3M13 25l-3 3" />
            <circle cx="34" cy="33" r="4" />
            <path d="M34 24v3M34 39v3M25 33h3M40 33h3M28 27l2 2M38 37l2 2M40 27l-2 2M30 37l-2 2" />
        </symbol>
        <symbol id="icon-calendar" viewBox="0 0 48 48">
            <path d="M10 13h28v27H10z" />
            <path d="M10 21h28M17 8v8M31 8v8" />
            <path d="M17 27h5M26 27h5M17 34h5M26 34h5" />
        </symbol>
        <symbol id="icon-support" viewBox="0 0 48 48">
            <path d="M12 31v-7a12 12 0 0 1 24 0v7" />
            <path d="M12 31h6v8h-4a4 4 0 0 1-4-4v-2a2 2 0 0 1 2-2Z" />
            <path d="M36 31h-6v8h4a4 4 0 0 0 4-4v-2a2 2 0 0 0-2-2Z" />
            <path d="M29 39c-2 2-5 3-9 2" />
        </symbol>
        <symbol id="icon-handshake" viewBox="0 0 48 48">
            <path d="M17 29 9 21l8-8 7 7" />
            <path d="m31 29 8-8-8-8-8 8" />
            <path d="M19 31h11l5-5" />
            <path d="M14 26l7 7a5 5 0 0 0 7 0l6-6" />
        </symbol>
        <symbol id="icon-shield" viewBox="0 0 48 48">
            <path d="M24 5 39 11v11c0 10-6 17-15 21C15 39 9 32 9 22V11l15-6Z" />
            <path d="m17 24 5 5 10-11" />
        </symbol>
        <symbol id="icon-chart" viewBox="0 0 48 48">
            <path d="M9 38h30" />
            <path d="M14 32v-7M24 32V15M34 32V22" />
            <path d="m12 21 8-7 8 4 9-9" />
        </symbol>
        <symbol id="icon-search" viewBox="0 0 48 48">
            <circle cx="21" cy="21" r="11" />
            <path d="m30 30 10 10" />
        </symbol>
        <symbol id="icon-clock" viewBox="0 0 48 48">
            <circle cx="24" cy="24" r="16" />
            <path d="M24 14v11l7 4" />
        </symbol>
        <symbol id="icon-smile" viewBox="0 0 48 48">
            <circle cx="24" cy="24" r="17" />
            <path d="M17 19h.1M31 19h.1" />
            <path d="M16 29c4 6 12 6 16 0" />
        </symbol>
    </svg>

    <main class="poster">
        <header class="topbar">
            <div class="brand" aria-label="Attica Gold Company">
                <span class="brand-logo"><svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true"><use href="#logo-mark"></use></svg></span>
                <span class="brand-name"><strong>Attica</strong><span>Gold Company</span></span>
            </div>
            <h1 class="title">ATTICA GOLD COMPANY - CALL CENTRE SCRIPT FLOWCHART</h1>
            <div class="brand" aria-label="Attica Gold Company">
                <span class="brand-logo"><svg width="34" height="34" viewBox="0 0 48 48" aria-hidden="true"><use href="#logo-mark"></use></svg></span>
                <span class="brand-name"><strong>Attica</strong><span>Gold Company</span></span>
            </div>
        </header>

        <section class="flow" aria-label="Call centre script flowchart">
            <div class="desktop-only" aria-hidden="true">
                <span class="flow-line branch-line left"></span>
                <span class="flow-line branch-line right"></span>
                <span class="flow-line connector-rate left"></span>
                <span class="flow-line connector-rate right"></span>
                <span class="flow-line connector-promise left"></span>
                <span class="flow-line connector-promise right"></span>
            </div>

            <div class="top-flow">
                <article class="node opening down">
                    <span class="icon blue"><svg aria-hidden="true"><use href="#icon-headset"></use></svg></span>
                    <div>
                        <h2>1. Opening Greeting</h2>
                        <p>Good Morning / Good Afternoon / Good Evening.</p>
                        <p class="lead">Thank you for calling Attica Gold Company.</p>
                        <p>My name is [Agent Name]. How may I assist you today?</p>
                    </div>
                </article>

                <article class="node service common">
                    <span class="icon orange"><svg aria-hidden="true"><use href="#icon-help"></use></svg></span>
                    <div>
                        <h2>2. Service Required</h2>
                        <p>Sir/Madam, may I know what service is required for you today?</p>
                        <div class="service-options">
                            <div class="option-pill"><span class="badge-number">1</span><span>Physical Gold Sell</span></div>
                            <div class="option-pill"><span class="badge-number">2</span><span>Release Gold &amp; Takeover with Best Live Gold Rate</span></div>
                        </div>
                    </div>
                </article>
            </div>

            <div class="branch-grid">
                <div class="branch green-branch">
                    <span class="path-marker">1</span>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-scale"></use></svg></span>
                        <div>
                            <h3>4A. Physical Gold Sell</h3>
                            <p>Customer wants to sell physical gold.</p>
                        </div>
                    </article>

                    <article class="node small-icon green promise-source promise-source-left">
                        <span class="promise-link" aria-hidden="true"></span>
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
                        <div>
                            <h3>5A. Collect Customer Details</h3>
                            <ul>
                                <li>May I know your name?</li>
                                <li>Contact number?</li>
                                <li>Location / Area?</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-necklace"></use></svg></span>
                        <div>
                            <h3>6A. Gold Information</h3>
                            <ul>
                                <li>Approx. gold weight?</li>
                                <li>Jewellery / Coins / Scrap?</li>
                                <li>Is the jewellery hallmarked?</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-document"></use></svg></span>
                        <div>
                            <h3>7A. Documents Required</h3>
                            <ul>
                                <li>Aadhaar Card</li>
                                <li>PAN Card</li>
                                <li>Gold Bill (if available)</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-upload"></use></svg></span>
                        <div>
                            <h3>7B. Upload Bills (If Available)</h3>
                            <p>If you have gold purchase bills, please upload them on our official WhatsApp. This helps us in faster valuation.</p>
                        </div>
                    </article>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-rupee"></use></svg></span>
                        <div>
                            <h3>8A. Payment Mode</h3>
                            <p>After valuation, how would you like to receive the payment?</p>
                            <ul>
                                <li>IMPS</li>
                                <li>NEFT</li>
                            </ul>
                            <p class="lead">(We do not provide cash payment)</p>
                        </div>
                    </article>

                    <article class="node small-icon green">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-location"></use></svg></span>
                        <div>
                            <h3>9A. Branch &amp; Visit Planning</h3>
                            <ul>
                                <li>Nearest branch details will be shared.</li>
                                <li>When are you planning to visit the branch?</li>
                                <li>Preferred date &amp; time?</li>
                            </ul>
                        </div>
                    </article>
                </div>

                <div class="center-stack">
                    <article class="node small-icon green rate-card">
                        <span class="icon green"><svg aria-hidden="true"><use href="#icon-gold"></use></svg></span>
                        <div>
                            <h3>3. Today's Live Gold Rate Update</h3>
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

                    <article class="node promise">
                        <span class="shield"><svg width="68" height="68" viewBox="0 0 48 48" aria-hidden="true"><use href="#icon-shield"></use></svg></span>
                        <h3>Our Promise</h3>
                        <div class="promise-list">
                            <div class="promise-item"><span class="mini-icon"><svg aria-hidden="true"><use href="#icon-chart"></use></svg></span><span>Best Live Gold Rate</span></div>
                            <div class="promise-item"><span class="mini-icon"><svg aria-hidden="true"><use href="#icon-search"></use></svg></span><span>Transparent Valuation</span></div>
                            <div class="promise-item"><span class="mini-icon"><svg aria-hidden="true"><use href="#icon-clock"></use></svg></span><span>Quick &amp; Easy Process</span></div>
                            <div class="promise-item"><span class="mini-icon"><svg aria-hidden="true"><use href="#icon-rupee"></use></svg></span><span>Instant Payment (as per mode)</span></div>
                            <div class="promise-item"><span class="mini-icon"><svg aria-hidden="true"><use href="#icon-smile"></use></svg></span><span>Customer Satisfaction</span></div>
                        </div>
                    </article>
                </div>

                <div class="branch purple-branch">
                    <span class="path-marker">2</span>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-bank"></use></svg></span>
                        <div>
                            <h3>4B. Release Gold &amp; Takeover</h3>
                            <p>Customer wants to release pledged gold &amp; takeover with best rate.</p>
                        </div>
                    </article>

                    <article class="node small-icon purple promise-source promise-source-right">
                        <span class="promise-link" aria-hidden="true"></span>
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-clipboard"></use></svg></span>
                        <div>
                            <h3>5B. Requirement Details</h3>
                            <ul>
                                <li>Which bank/finance company?</li>
                                <li>Approx. loan amount?</li>
                                <li>Approx. gold weight?</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-document"></use></svg></span>
                        <div>
                            <h3>6B. Documents Required</h3>
                            <p>To book the slot, kindly keep ready:</p>
                            <ul>
                                <li>Gold Loan Document / Pledge Receipt</li>
                                <li>Aadhaar Card</li>
                                <li>PAN Card</li>
                                <li>Cancelled Cheque</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-upload"></use></svg></span>
                        <div>
                            <h3>6C. Upload Documents (Important)</h3>
                            <p>Please upload the above documents directly on our official WhatsApp number.</p>
                            <p class="lead">This is mandatory to book your slot.</p>
                        </div>
                    </article>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-gears"></use></svg></span>
                        <div>
                            <h3>7B. Process Explanation</h3>
                            <ul>
                                <li>Verify loan documents</li>
                                <li>Evaluate pledged gold</li>
                                <li>Check live gold valuation</li>
                                <li>Assist in release &amp; takeover process</li>
                                <li>Provide best possible live gold rate</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-rupee"></use></svg></span>
                        <div>
                            <h3>8B. Payment Mode</h3>
                            <p>After successful release &amp; valuation, how would you prefer payment?</p>
                            <ul>
                                <li>IMPS</li>
                                <li>NEFT</li>
                            </ul>
                            <p class="lead">(We do not provide cash payment)</p>
                        </div>
                    </article>

                    <article class="node small-icon purple">
                        <span class="icon purple"><svg aria-hidden="true"><use href="#icon-calendar"></use></svg></span>
                        <div>
                            <h3>9B. Slot Booking &amp; Visit Planning</h3>
                            <ul>
                                <li>Preferred date &amp; time for slot booking?</li>
                                <li>Share WhatsApp number</li>
                                <li>Branch details &amp; slot confirmation will be shared</li>
                            </ul>
                        </div>
                    </article>
                </div>
            </div>

            <div class="shared-flow">
                <div class="shared-stack">
                    <article class="node small-icon common down">
                        <span class="icon orange"><svg aria-hidden="true"><use href="#icon-bank"></use></svg></span>
                        <div>
                            <h3>10. Branch Details Sharing</h3>
                            <ul>
                                <li>Branch Address</li>
                                <li>Landmarks</li>
                                <li>Working Hours</li>
                                <li>Location link will be shared on WhatsApp / SMS</li>
                            </ul>
                        </div>
                    </article>

                    <article class="node small-icon support down">
                        <span class="icon red"><svg aria-hidden="true"><use href="#icon-support"></use></svg></span>
                        <div>
                            <h3>11. Customer Support</h3>
                            <p>If you face any issue, please contact our customer support:</p>
                            <p class="support-number">8880 300 300</p>
                        </div>
                    </article>

                    <article class="node small-icon close">
                        <span class="icon cyan"><svg aria-hidden="true"><use href="#icon-handshake"></use></svg></span>
                        <div>
                            <h3>12. Closing</h3>
                            <ul>
                                <li>Is there anything else I can assist you with?</li>
                                <li>Thank you for calling Attica Gold Company.</li>
                                <li>Have a wonderful day!</li>
                            </ul>
                        </div>
                    </article>
                </div>

                <aside class="legend" aria-label="Legend">
                    <h3>Legend</h3>
                    <div class="legend-row"><span class="swatch green"></span><span>Physical Gold Sell Flow</span></div>
                    <div class="legend-row"><span class="swatch purple"></span><span>Release Gold &amp; Takeover Flow</span></div>
                    <div class="legend-row"><span class="swatch orange"></span><span>Common / Shared Steps</span></div>
                    <div class="legend-row"><span class="swatch red"></span><span>Support</span></div>
                    <div class="legend-row"><span class="swatch cyan"></span><span>Closure</span></div>
                </aside>
            </div>

            <p class="note">Note: Gold rate is subject to change as per live market. Final valuation will be done at the branch.</p>
        </section>
    </main>
</body>

</html>
