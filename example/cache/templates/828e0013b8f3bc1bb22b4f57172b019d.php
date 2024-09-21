<?php class_exists('Pionia\Templating\TemplateEngine') or exit; ?>
<?php

use Pionia\Collections\Carbon;
use Pionia\Utils\Support;
?>
<!doctype html>

<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo app->getAppName() ?> Works</title>
    <meta name="description" content="Pionia Framework">
    <meta name="author" content="Jete Ezra">
    <link rel="favicon" href="./favicon.ico">
    <style>
        <?php echo file_get_contents(asset('bootstrap.min.css'));  ?>

        .bg-pionia {
            background: url('./pionia_logo.webp');
            background-position: center;
            background-repeat: no-repeat;
            backdrop-filter: blur(10px)
        }
        .bg-inherit	{ background-color: inherit; }
        .bg-current	{ background-color: currentColor; }
        .bg-transparent	{ background-color: transparent; }
        .bg-black	{ background-color: rgb(0 0 0); }
        .bg-white	{ background-color: rgb(255 255 255); }
        .bg-slate-50	{ background-color: rgb(248 250 252); }
        .bg-slate-100	{ background-color: rgb(241 245 249); }
        .bg-slate-200	{ background-color: rgb(226 232 240); }
        .bg-slate-300	{ background-color: rgb(203 213 225); }
        .bg-slate-400	{ background-color: rgb(148 163 184); }
        .bg-slate-500	{ background-color: rgb(100 116 139); }
        .bg-slate-600	{ background-color: rgb(71 85 105); }
        .bg-slate-700	{ background-color: rgb(51 65 85); }
        .bg-slate-800	{ background-color: rgb(30 41 59); }
        .bg-slate-900	{ background-color: rgb(15 23 42); }
        .bg-slate-950	{ background-color: rgb(2 6 23); }
        .bg-gray-50	{ background-color: rgb(249 250 251); }
        .bg-gray-100	{ background-color: rgb(243 244 246); }
        .bg-gray-200	{ background-color: rgb(229 231 235); }
        .bg-gray-300	{ background-color: rgb(209 213 219); }
        .bg-gray-400	{ background-color: rgb(156 163 175); }
        .bg-gray-500	{ background-color: rgb(107 114 128); }
        .bg-gray-600	{ background-color: rgb(75 85 99); }
        .bg-gray-700	{ background-color: rgb(55 65 81); }
        .bg-gray-800	{ background-color: rgb(31 41 55); }
        .bg-gray-900	{ background-color: rgb(17 24 39); }
        .bg-gray-950	{ background-color: rgb(3 7 18); }
        .bg-zinc-50	{ background-color: rgb(250 250 250); }
        .bg-zinc-100	{ background-color: rgb(244 244 245); }
        .bg-zinc-200	{ background-color: rgb(228 228 231); }
        .bg-zinc-300	{ background-color: rgb(212 212 216); }
        .bg-zinc-400	{ background-color: rgb(161 161 170); }
        .bg-zinc-500	{ background-color: rgb(113 113 122); }
        .bg-zinc-600	{ background-color: rgb(82 82 91); }
        .bg-zinc-700	{ background-color: rgb(63 63 70); }
        .bg-zinc-800	{ background-color: rgb(39 39 42); }
        .bg-zinc-900	{ background-color: rgb(24 24 27); }
        .bg-zinc-950	{ background-color: rgb(9 9 11); }
        .bg-neutral-50	{ background-color: rgb(250 250 250); }
        .bg-neutral-100	{ background-color: rgb(245 245 245); }
        .bg-neutral-200	{ background-color: rgb(229 229 229); }
        .bg-neutral-300	{ background-color: rgb(212 212 212); }
        .bg-neutral-400	{ background-color: rgb(163 163 163); }
        .bg-neutral-500	{ background-color: rgb(115 115 115); }
        .bg-neutral-600	{ background-color: rgb(82 82 82); }
        .bg-neutral-700	{ background-color: rgb(64 64 64); }
        .bg-neutral-800	{ background-color: rgb(38 38 38); }
        .bg-neutral-900	{ background-color: rgb(23 23 23); }
        .bg-neutral-950	{ background-color: rgb(10 10 10); }
        .bg-stone-50	{ background-color: rgb(250 250 249); }
        .bg-stone-100	{ background-color: rgb(245 245 244); }
        .bg-stone-200	{ background-color: rgb(231 229 228); }
        .bg-stone-300	{ background-color: rgb(214 211 209); }
        .bg-stone-400	{ background-color: rgb(168 162 158); }
        .bg-stone-500	{ background-color: rgb(120 113 108); }
        .bg-stone-600	{ background-color: rgb(87 83 78); }
        .bg-stone-700	{ background-color: rgb(68 64 60); }
        .bg-stone-800	{ background-color: rgb(41 37 36); }
        .bg-stone-900	{ background-color: rgb(28 25 23); }
        .bg-stone-950	{ background-color: rgb(12 10 9); }
        .bg-red-50	{ background-color: rgb(254 242 242); }
        .bg-red-100	{ background-color: rgb(254 226 226); }
        .bg-red-200	{ background-color: rgb(254 202 202); }
        .bg-red-300	{ background-color: rgb(252 165 165); }
        .bg-red-400	{ background-color: rgb(248 113 113); }
        .bg-red-500	{ background-color: rgb(239 68 68); }
        .bg-red-600	{ background-color: rgb(220 38 38); }
        .bg-red-700	{ background-color: rgb(185 28 28); }
        .bg-red-800	{ background-color: rgb(153 27 27); }
        .bg-red-900	{ background-color: rgb(127 29 29); }
        .bg-red-950	{ background-color: rgb(69 10 10); }
        .bg-orange-50	{ background-color: rgb(255 247 237); }
        .bg-orange-100	{ background-color: rgb(255 237 213); }
        .bg-orange-200	{ background-color: rgb(254 215 170); }
        .bg-orange-300	{ background-color: rgb(253 186 116); }
        .bg-orange-400	{ background-color: rgb(251 146 60); }
        .bg-orange-500	{ background-color: rgb(249 115 22); }
        .bg-orange-600	{ background-color: rgb(234 88 12); }
        .bg-orange-700	{ background-color: rgb(194 65 12); }
        .bg-orange-800	{ background-color: rgb(154 52 18); }
        .bg-orange-900	{ background-color: rgb(124 45 18); }
        .bg-orange-950	{ background-color: rgb(67 20 7); }
        .bg-amber-50	{ background-color: rgb(255 251 235); }
        .bg-amber-100	{ background-color: rgb(254 243 199); }
        .bg-amber-200	{ background-color: rgb(253 230 138); }
        .bg-amber-300	{ background-color: rgb(252 211 77); }
        .bg-amber-400	{ background-color: rgb(251 191 36); }
        .bg-amber-500	{ background-color: rgb(245 158 11); }
        .bg-amber-600	{ background-color: rgb(217 119 6); }
        .bg-amber-700	{ background-color: rgb(180 83 9); }
        .bg-amber-800	{ background-color: rgb(146 64 14); }
        .bg-amber-900	{ background-color: rgb(120 53 15); }
        .bg-amber-950	{ background-color: rgb(69 26 3); }
        .bg-yellow-50	{ background-color: rgb(254 252 232); }
        .bg-yellow-100	{ background-color: rgb(254 249 195); }
        .bg-yellow-200	{ background-color: rgb(254 240 138); }
        .bg-yellow-300	{ background-color: rgb(253 224 71); }
        .bg-yellow-400	{ background-color: rgb(250 204 21); }
        .bg-yellow-500	{ background-color: rgb(234 179 8); }
        .bg-yellow-600	{ background-color: rgb(202 138 4); }
        .bg-yellow-700	{ background-color: rgb(161 98 7); }
        .bg-yellow-800	{ background-color: rgb(133 77 14); }
        .bg-yellow-900	{ background-color: rgb(113 63 18); }
        .bg-yellow-950	{ background-color: rgb(66 32 6); }
        .bg-lime-50	{ background-color: rgb(247 254 231); }
        .bg-lime-100	{ background-color: rgb(236 252 203); }
        .bg-lime-200	{ background-color: rgb(217 249 157); }
        .bg-lime-300	{ background-color: rgb(190 242 100); }
        .bg-lime-400	{ background-color: rgb(163 230 53); }
        .bg-lime-500	{ background-color: rgb(132 204 22); }
        .bg-lime-600	{ background-color: rgb(101 163 13); }
        .bg-lime-700	{ background-color: rgb(77 124 15); }
        .bg-lime-800	{ background-color: rgb(63 98 18); }
        .bg-lime-900	{ background-color: rgb(54 83 20); }
        .bg-lime-950	{ background-color: rgb(26 46 5); }
        .bg-green-50	{ background-color: rgb(240 253 244); }
        .bg-green-100	{ background-color: rgb(220 252 231); }
        .bg-green-200	{ background-color: rgb(187 247 208); }
        .bg-green-300	{ background-color: rgb(134 239 172); }
        .bg-green-400	{ background-color: rgb(74 222 128); }
        .bg-green-500	{ background-color: rgb(34 197 94); }
        .bg-green-600	{ background-color: rgb(22 163 74); }
        .bg-green-700	{ background-color: rgb(21 128 61); }
        .bg-green-800	{ background-color: rgb(22 101 52); }
        .bg-green-900	{ background-color: rgb(20 83 45); }
        .bg-green-950	{ background-color: rgb(5 46 22); }
        ..bg-emerald-50	{ background-color: rgb(236 253 245); }
        .bg-emerald-100	{ background-color: rgb(209 250 229); }
        .bg-emerald-200	{ background-color: rgb(167 243 208); }
        .bg-emerald-300	{ background-color: rgb(110 231 183); }
        .bg-emerald-400	{ background-color: rgb(52 211 153); }
        .bg-emerald-500	{ background-color: rgb(16 185 129); }
        .bg-emerald-600	{ background-color: rgb(5 150 105); }
        .bg-emerald-700	{ background-color: rgb(4 120 87); }
        .bg-emerald-800	{ background-color: rgb(6 95 70); }
        .bg-emerald-900	{ background-color: rgb(6 78 59); }
        .bg-emerald-950	{ background-color: rgb(2 44 34); }
        .bg-teal-50	{ background-color: rgb(240 253 250); }
        .bg-teal-100	{ background-color: rgb(204 251 241); }
        .bg-teal-200	{ background-color: rgb(153 246 228); }
        .bg-teal-300	{ background-color: rgb(94 234 212); }
        .bg-teal-400	{ background-color: rgb(45 212 191); }
        .bg-teal-500	{ background-color: rgb(20 184 166); }
        .bg-teal-600	{ background-color: rgb(13 148 136); }
        .bg-teal-700	{ background-color: rgb(15 118 110); }
        .bg-teal-800	{ background-color: rgb(17 94 89); }
        .bg-teal-900	{ background-color: rgb(19 78 74); }
        .bg-teal-950	{ background-color: rgb(4 47 46); }
        .bg-cyan-50	{ background-color: rgb(236 254 255); }
        .bg-cyan-100	{ background-color: rgb(207 250 254); }
        .bg-cyan-200	{ background-color: rgb(165 243 252); }
        .bg-cyan-300	{ background-color: rgb(103 232 249); }
        .bg-cyan-400	{ background-color: rgb(34 211 238); }
        .bg-cyan-500	{ background-color: rgb(6 182 212); }
        .bg-cyan-600	{ background-color: rgb(8 145 178); }
        .bg-cyan-700	{ background-color: rgb(14 116 144); }
        .bg-cyan-800	{ background-color: rgb(21 94 117); }
        .bg-cyan-900	{ background-color: rgb(22 78 99); }
        .bg-cyan-950	{ background-color: rgb(8 51 68); }
        .bg-sky-50	{ background-color: rgb(240 249 255); }
        .bg-sky-100	{ background-color: rgb(224 242 254); }
        .bg-sky-200	{ background-color: rgb(186 230 253); }
        .bg-sky-300	{ background-color: rgb(125 211 252); }
        .bg-sky-400	{ background-color: rgb(56 189 248); }
        .bg-sky-500	{ background-color: rgb(14 165 233); }
        .bg-sky-600	{ background-color: rgb(2 132 199); }
        .bg-sky-700	{ background-color: rgb(3 105 161); }
        .bg-sky-800	{ background-color: rgb(7 89 133); }
        .bg-sky-900	{ background-color: rgb(12 74 110); }
        .bg-sky-950	{ background-color: rgb(8 47 73); }
        .bg-blue-50	{ background-color: rgb(239 246 255); }
        .bg-blue-100	{ background-color: rgb(219 234 254); }
        .bg-blue-200	{ background-color: rgb(191 219 254); }
        .bg-blue-300	{ background-color: rgb(147 197 253); }
        .bg-blue-400	{ background-color: rgb(96 165 250); }
        .bg-blue-500	{ background-color: rgb(59 130 246); }
        .bg-blue-600	{ background-color: rgb(37 99 235); }
        .bg-blue-700	{ background-color: rgb(29 78 216); }
        .bg-blue-800	{ background-color: rgb(30 64 175); }
        .bg-blue-900	{ background-color: rgb(30 58 138); }
        .bg-blue-950	{ background-color: rgb(23 37 84); }
        .bg-indigo-50	{ background-color: rgb(238 242 255); }
        .bg-indigo-100	{ background-color: rgb(224 231 255); }
        .bg-indigo-200	{ background-color: rgb(199 210 254); }
        .bg-indigo-300	{ background-color: rgb(165 180 252); }
        .bg-indigo-400	{ background-color: rgb(129 140 248); }
        .bg-indigo-500	{ background-color: rgb(99 102 241); }
        .bg-indigo-600	{ background-color: rgb(79 70 229); }
        .bg-indigo-700	{ background-color: rgb(67 56 202); }
        .bg-indigo-800	{ background-color: rgb(55 48 163); }
        .bg-indigo-900	{ background-color: rgb(49 46 129); }
        .bg-indigo-950	{ background-color: rgb(30 27 75); }
        .bg-violet-50	{ background-color: rgb(245 243 255); }
        .bg-violet-100	{ background-color: rgb(237 233 254); }
        .bg-violet-200	{ background-color: rgb(221 214 254); }
        .bg-violet-300	{ background-color: rgb(196 181 253); }
        .bg-violet-400	{ background-color: rgb(167 139 250); }
        .bg-violet-500	{ background-color: rgb(139 92 246); }
        .bg-violet-600	{ background-color: rgb(124 58 237); }
        .bg-violet-700	{ background-color: rgb(109 40 217); }
        .bg-violet-800	{ background-color: rgb(91 33 182); }
        .bg-violet-900	{ background-color: rgb(76 29 149); }
        .bg-violet-950	{ background-color: rgb(46 16 101); }
        .bg-purple-50	{ background-color: rgb(250 245 255); }
        .bg-purple-100	{ background-color: rgb(243 232 255); }
        .bg-purple-200	{ background-color: rgb(233 213 255); }
        .bg-purple-300	{ background-color: rgb(216 180 254); }
        .bg-purple-400	{ background-color: rgb(192 132 252); }
        .bg-purple-500	{ background-color: rgb(168 85 247); }
        .bg-purple-600	{ background-color: rgb(147 51 234); }
        .bg-purple-700	{ background-color: rgb(126 34 206); }
        .bg-purple-800	{ background-color: rgb(107 33 168); }
        .bg-purple-900	{ background-color: rgb(88 28 135); }
        .bg-purple-950	{ background-color: rgb(59 7 100); }
        .bg-fuchsia-50	{ background-color: rgb(253 244 255); }
        .bg-fuchsia-100	{ background-color: rgb(250 232 255); }
        .bg-fuchsia-200	{ background-color: rgb(245 208 254); }
        .bg-fuchsia-300	{ background-color: rgb(240 171 252); }
        .bg-fuchsia-400	{ background-color: rgb(232 121 249); }
        .bg-fuchsia-500	{ background-color: rgb(217 70 239); }
        .bg-fuchsia-600	{ background-color: rgb(192 38 211); }
        .bg-fuchsia-700	{ background-color: rgb(162 28 175); }
        .bg-fuchsia-800	{ background-color: rgb(134 25 143); }
        .bg-fuchsia-900	{ background-color: rgb(112 26 117); }
        .bg-fuchsia-950	{ background-color: rgb(74 4 78); }
        .bg-pink-50	{ background-color: rgb(253 242 248); }
        .bg-pink-100	{ background-color: rgb(252 231 243); }
        .bg-pink-200	{ background-color: rgb(251 207 232); }
        .bg-pink-300	{ background-color: rgb(249 168 212); }
        .bg-pink-400	{ background-color: rgb(244 114 182); }
        .bg-pink-500	{ background-color: rgb(236 72 153); }
        .bg-pink-600	{ background-color: rgb(219 39 119); }
        .bg-pink-700	{ background-color: rgb(190 24 93); }
        .bg-pink-800	{ background-color: rgb(157 23 77); }
        .bg-pink-900	{ background-color: rgb(131 24 67); }
        .bg-pink-950	{ background-color: rgb(80 7 36); }
        .bg-rose-50	{ background-color: rgb(255 241 242); }
        .bg-rose-100	{ background-color: rgb(255 228 230); }
        .bg-rose-200	{ background-color: rgb(254 205 211); }
        .bg-rose-300	{ background-color: rgb(253 164 175); }
        .bg-rose-400	{ background-color: rgb(251 113 133); }
        .bg-rose-500	{ background-color: rgb(244 63 94); }
        .bg-rose-600	{ background-color: rgb(225 29 72); }
        .bg-rose-700	{ background-color: rgb(190 18 60); }
        .bg-rose-800	{ background-color: rgb(159 18 57); }
        .bg-rose-900	{ background-color: rgb(136 19 55); }
        .bg-rose-950	{ background-color: rgb(76 5 25); }
    </style>
</head>
<body class="flex-1 d-flex bg-primary-subtle bg-pionia">
<div class="container font-monospace">
    <div class="d-flex justify-content-start align-items-center gap-2 py-5">
        <img src="./favicon.ico" class="rounded " alt="Pionia Framework" >
        <div>
            <h1 class="text-success text-center font-monospace">🚀 <?php echo $app->getAppName() ?> application is active 🚀</h1>
            <h6 class="font-monospace">Environment: <?php echo env('APP_ENV') ?></h6>
            <h6 class="font-monospace">DEBUG: <?php echo yesNo(env('DEBUG')) ?></h6>
            <?php if (!$app->welcomePageSettings()->get('HIDE_PORT', false)): ?>
            <h6 class="font-monospace">PORT: <?php echo env('SERVER_PORT') ?></h6>
            <?php endif ?>
            <h6 class="font-monospace">START TIME:<?= Carbon::createFromTimestamp(PIONIA_START)->toString()?></h6>
        </div>
    </div>

    <?php if (!$app->welcomePageSettings()->get('HIDE_QUICK_START', false)): ?>
        <hr class="bg-pink-500">
        <h1 class="text-secondary-emphasis my-2 font-monospace">💫 Quick Start</h1>
        <div class="row gap-4 flex-wrap row-cols-3 align-content-center">
        <div class="bg-zinc-300 rounded">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1 font-monospace">Pionia CLI:</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
                <pre class="text-center text-black h4 fw-bolder font-monospace">php pionia </pre>
            </div>
            <p class="text-center text-black-50 text-wrap text-break">To view all available commands in the Pionia </p>
        </div>

        <div class="bg-zinc-300 rounded">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1">Pionia Services</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
            <pre class="text-center text-black h4 fw-bolder">php pionia gen:service name </pre>
            </div>
            <p class="text-center text-black-50 ">These can be basic services or generics</p>
        </div>

        <div class="bg-zinc-300  rounded">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1">Pionia Switches</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
            <pre class="text-center text-black h4 fw-bolder">php pionia make:switch name </pre>
            </div>
            <p class="text-center text-black-50">These register services that are under a specific version</p>
        </div>

        <div class="bg-zinc-300 rounded mr-1">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1">Pionia Aliases</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
            <pre class="text-center text-black h4 fw-bolder">php pionia app:aliases </pre>
            </div>
            <p class="text-center text-black-50">View all aliases in the context</p>
        </div>

        <div class="bg-zinc-300 rounded">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1">Pionia Authentication</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
            <pre class="text-center text-black h4 fw-bolder">php pionia make:auth name </pre>
            </div>
            <p class="text-center text-black-50">Add any authentication you prefer for your API</p>
        </div>

        <div class="bg-zinc-300 rounded">
            <p class="text-center text-dark text-emphasis bg-rose-50 text-small rounded-1">Pionia Caching</p>
            <div class="flex d-flex align-items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="19" fill="currentColor" class="bi bi-terminal" viewBox="0 0 16 16">
                    <path d="M6 9a.5.5 0 0 1 .5-.5h3a.5.5 0 0 1 0 1h-3A.5.5 0 0 1 6 9M3.854 4.146a.5.5 0 1 0-.708.708L4.793 6.5 3.146 8.146a.5.5 0 1 0 .708.708l2-2a.5.5 0 0 0 0-.708z"/>
                    <path d="M2 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2zm12 1a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/>
                </svg>
            <pre class="text-center text-black h4 fw-bolder">php pionia cache </pre>
            </div>
            <p class="text-center text-black-50">For all commands available for caching</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$app->welcomePageSettings()->get('HIDE_CONTEXT', false)): ?>
        <hr class="bg-pink-500">
    <h1 class="text-secondary-emphasis my-2">🏝️ Context</h1>
    <div class="row gap-2 align-content-center">
        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white text-emphasis text-small rounded-1">Middlewares</p>
            <p class="text-center text-white h3 "><?php echo $app->getSilently('middlewares')?->size() ?></p>
        </div>

        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white  text-emphasis text-small  rounded-1">Authentications</p>
            <p class="text-center text-white h3 "><?php echo $app->getSilently('authentications')?->size() ?></p>
        </div>

        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white bg-pink-500 text-emphasis text-small  rounded-1">Routes</p>
            <p class="text-center text-white h3 "><?php echo $app->getSilently('routes')?->size() ?></p>
        </div>

        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white bg-pink-500 text-emphasis text-small  rounded-1">Aliases</p>
            <p class="text-center text-white h3 "><?php echo $app->getSilently('aliases')?->size() ?></p>
        </div>

        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white bg-pink-500 text-emphasis text-small  rounded-1">Commands</p>
            <p class="text-center text-white h3 "><?php echo $app->getSilently('commands')?->size() ?></p>
        </div>

        <div class="bg-pink-500 col-2 rounded">
            <p class="text-center text-white bg-pink-500 text-emphasis text-small  rounded-1">Db Connections</p>
            <p class="text-center text-white h3 "><?php echo $app->getDiscoveredConnections()->size() ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($app->isDebug() && !$app->welcomePageSettings()->get('HIDE_ENV', false)): ?>
        <hr class="bg-pink-500">
        <h1 class="text-secondary-emphasis my-2">✅ Environment Variables:</h1>
    <div class="border border-danger rounded-1 p-2 bg-slate-200 text-wrap">
        <?php foreach (env()->all() as $key => $value): ?>
                <div class="d-flex gap-2">
            <p class="text-center text-white bg-black text-emphasis text-small p-2 align-items-center align-content-center  justify-content-center rounded-1"><?php echo $key ?></p>
            <p class="text-left text-primary text-sm text-wrap text-break"><?php echo is_array($value) ? Support::jsonify($value) : $value ?></p></div>

        <?php endforeach; ?>
    </div>
    <?php endif; ?>


    <div class="text-dark-emphasis font-monospace mx-auto align-middle w-50 text-center">
        <hr>
           This framework is looking for sponsorship. If you are interested in sponsoring this project, please reach out on
        <a href="https://www.linkedin.com/in/jetezra/" target="_blank" class="text-info">LinkedIn</a>.
    </div>
</div>
</body>
</html>
