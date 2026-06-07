<?php
require_once __DIR__ . '/core/models/MatchModel.php';
require_once __DIR__ . '/core/models/Prediction.php';
require_once __DIR__ . '/core/models/Setting.php';

$matchModel = new MatchModel();
$predictionModel = new Prediction();
$settingModel = new Setting();
$matches = $matchModel->activeForPrediction();
$status = null;
$message = '';
$selectedMatchId = (int)($_POST['match_id'] ?? ($_GET['match_id'] ?? ($matches[0]['id'] ?? 0)));
$phoneNumber = (string)$settingModel->get('phone_number', '+98 21 1234 5678');
$callNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
            throw new RuntimeException('درخواست نامعتبر است.');
        }
        $selectedMatchId = (int)($_POST['match_id'] ?? 0);
        $predictionModel->createWithCrmMatch([
            'customer_name' => sanitizeInput($_POST['customer_name'] ?? ''),
            'customer_last_name' => sanitizeInput($_POST['customer_last_name'] ?? ''),
            'mobile' => sanitizeInput($_POST['customer_mobile'] ?? ''),
            'match_id' => $selectedMatchId,
            'predicted_team_one_score' => (int)($_POST['predicted_team_one_score'] ?? -1),
            'predicted_team_two_score' => (int)($_POST['predicted_team_two_score'] ?? -1),
            'wants_reservation' => !empty($_POST['wants_reservation']) ? 1 : 0,
            'source' => substr((string)($_SERVER['HTTP_REFERER'] ?? 'prediction.php'), 0, 150),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
        $status = 'success';
        $message = 'پیش‌بینی شما ثبت شد. برای رزرو سریع میز در زمان پخش مسابقه، همین حالا با رستوران کی تماس بگیرید.';
        $matches = $matchModel->activeForPrediction();
    } catch (Throwable $e) {
        $status = 'error';
        $message = $e->getMessage();
    }
}

$token = generateCSRFToken();
$matchPayload = [];
foreach ($matches as $match) {
    $teamOne = $match['display_team_one'] ?? $match['team_one_name'] ?? $match['team_a'] ?? '';
    $teamTwo = $match['display_team_two'] ?? $match['team_two_name'] ?? $match['team_b'] ?? '';
    $matchPayload[(int)$match['id']] = [
        'teamOne' => $teamOne,
        'teamTwo' => $teamTwo,
        'title' => $match['title'] ?: ($teamOne . ' - ' . $teamTwo),
        'start' => formatJalaliDateTime($match['display_match_start_at'] ?? ($match['match_start_at'] ?? $match['match_date'] ?? ''), true),
        'deadline' => formatJalaliDateTime($match['display_prediction_end_at'] ?? ($match['prediction_end_at'] ?? $match['prediction_close_at'] ?? ''), true),
        'points' => (int)($match['display_points_reward'] ?? $match['points_reward'] ?? $match['reward_points'] ?? 0),
        'logoOne' => $match['team_one_logo'] ?? '',
        'logoTwo' => $match['team_two_logo'] ?? '',
    ];
}
?>
<!doctype html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php echo renderSeoMeta('پیش‌بینی مسابقه | KEY', 'ثبت پیش‌بینی مسابقات ویژه مشتریان KEY', assetUrl('assets/images/home-preview.svg'), BASE_URL . '/prediction.php'); ?>
    <?php echo localFontPreloadLinks(); ?>
    <style>
        @font-face{font-family:Vazirmatn;src:url('assets/fonts/Vazirmatn-Regular.woff2') format('woff2');font-display:swap}
        :root{--ink:#102a2b;--deep:#003f3f;--gold:#d4af37;--soft:#f7f3ea;--line:#e5dfcf}
        *{box-sizing:border-box}
        body{margin:0;font-family:Vazirmatn,Tahoma,Arial;background:linear-gradient(135deg,#f8f5ee 0%,#eef5f3 55%,#fff 100%);color:var(--ink)}
        .wrap{min-height:100vh;display:grid;place-items:center;padding:32px 16px}
        .shell{width:min(1060px,100%);display:grid;grid-template-columns:.95fr 1.05fr;gap:18px;align-items:stretch}
        .panel{background:rgba(255,255,255,.88);border:1px solid rgba(212,175,55,.28);border-radius:16px;box-shadow:0 24px 70px rgba(0,50,50,.12);overflow:hidden}
        .intro{background:#003f3f;color:#fff;padding:30px;position:relative}
        .intro h1{margin:0 0 12px;font-size:clamp(30px,5vw,58px);line-height:1.12;letter-spacing:0}
        .intro p{margin:0;color:#ecf7f3;line-height:1.9}
        .match-list{display:grid;gap:12px;margin-top:24px}
        .match-card{border:1px solid rgba(255,255,255,.22);border-radius:12px;padding:14px;background:rgba(255,255,255,.08)}
        .match-title{font-weight:800;margin-bottom:8px}
        .teams{display:flex;align-items:center;justify-content:space-between;gap:12px}
        .team{display:flex;align-items:center;gap:8px;min-width:0}
        .team img{width:34px;height:34px;border-radius:50%;object-fit:cover;background:#fff}
        .team span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .vs{color:var(--gold);font-weight:900}
        .meta{font-size:13px;color:#d9eee8;margin-top:8px}
        .form-panel{padding:26px}
        .alert{padding:14px;border-radius:12px;margin-bottom:16px;line-height:1.8}
        .success{background:#dff4e8;color:#14532d;border:1px solid #b8e4ca}
        .error{background:#fde2e2;color:#7f1d1d;border:1px solid #f5b5b5}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .form-group{margin-bottom:14px}
        label{display:block;margin-bottom:7px;font-weight:800;color:#183c3d}
        input,select{width:100%;border:1px solid var(--line);border-radius:10px;padding:12px 13px;background:#fff;color:#132;min-height:46px}
        input:focus,select:focus{outline:2px solid rgba(212,175,55,.35);border-color:var(--gold)}
        .score-board{display:grid;grid-template-columns:1fr 70px 1fr;gap:10px;align-items:end;margin:12px 0 16px}
        .readonly-team{padding:12px;border-radius:10px;background:var(--soft);border:1px solid var(--line);font-weight:900;min-height:46px}
        .dash{text-align:center;font-size:28px;color:var(--gold);padding-bottom:8px}
        .check{display:flex;gap:10px;align-items:center;background:#f8faf9;border:1px solid var(--line);border-radius:10px;padding:12px}
        .check input{width:auto;min-height:auto}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;border:0;border-radius:10px;background:var(--deep);color:#fff;padding:13px 18px;text-decoration:none;font-weight:900;cursor:pointer;min-height:48px}
        .btn.call{background:var(--gold);color:#172}
        .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
        .back{display:inline-block;margin-top:16px;color:var(--deep);text-decoration:none}
        @media(max-width:820px){.shell{grid-template-columns:1fr}.intro{padding:24px}.form-panel{padding:20px}.grid,.score-board{grid-template-columns:1fr}.dash{display:none}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="shell">
        <aside class="panel intro">
            <h1>پیش‌بینی مسابقه</h1>
            <p>مسابقه را انتخاب کنید، نتیجه را حدس بزنید و اگر دوست دارید میز تماشای مسابقه را هم رزرو کنید.</p>
            <div class="match-list">
                <?php if (!$matches): ?>
                    <div class="match-card">در حال حاضر مسابقه فعالی برای پیش‌بینی وجود ندارد.</div>
                <?php endif; ?>
                <?php foreach ($matches as $match): $payload = $matchPayload[(int)$match['id']]; ?>
                    <div class="match-card">
                        <div class="match-title"><?php echo htmlspecialchars($payload['title']); ?></div>
                        <div class="teams">
                            <div class="team">
                                <?php if ($payload['logoOne']): ?><img src="<?php echo htmlspecialchars('/uploads/matches/' . basename($payload['logoOne'])); ?>" alt=""><?php endif; ?>
                                <span><?php echo htmlspecialchars($payload['teamOne']); ?></span>
                            </div>
                            <div class="vs">VS</div>
                            <div class="team">
                                <span><?php echo htmlspecialchars($payload['teamTwo']); ?></span>
                                <?php if ($payload['logoTwo']): ?><img src="<?php echo htmlspecialchars('/uploads/matches/' . basename($payload['logoTwo'])); ?>" alt=""><?php endif; ?>
                            </div>
                        </div>
                        <div class="meta">شروع: <?php echo htmlspecialchars($payload['start']); ?> | مهلت پیش‌بینی: <?php echo htmlspecialchars($payload['deadline']); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="panel form-panel">
            <?php if ($status): ?><div class="alert <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
            <?php if ($status === 'success'): ?>
                <div class="actions">
                    <?php if ($callNumber): ?><a class="btn call" href="tel:<?php echo htmlspecialchars($callNumber); ?>">تماس برای رزرو سریع</a><?php endif; ?>
                    <a class="btn" href="prediction.php">ثبت پیش‌بینی دیگر</a>
                </div>
            <?php endif; ?>
            <?php if ($matches): ?>
                <form method="post">
                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label>انتخاب مسابقه</label>
                        <select id="match-select" name="match_id" required>
                            <?php foreach ($matches as $match): $payload = $matchPayload[(int)$match['id']]; ?>
                                <option value="<?php echo (int)$match['id']; ?>" <?php echo (int)$match['id'] === $selectedMatchId ? 'selected' : ''; ?> data-team-one="<?php echo htmlspecialchars($payload['teamOne']); ?>" data-team-two="<?php echo htmlspecialchars($payload['teamTwo']); ?>">
                                    <?php echo htmlspecialchars($payload['title'] . ' | ' . $payload['start']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="score-board">
                        <div>
                            <label>تیم اول</label>
                            <div class="readonly-team" id="team-one-name">-</div>
                            <input type="number" min="0" max="30" name="predicted_team_one_score" placeholder="گل" required>
                        </div>
                        <div class="dash">:</div>
                        <div>
                            <label>تیم دوم</label>
                            <div class="readonly-team" id="team-two-name">-</div>
                            <input type="number" min="0" max="30" name="predicted_team_two_score" placeholder="گل" required>
                        </div>
                    </div>
                    <div class="grid">
                        <div class="form-group"><label>نام</label><input name="customer_name" autocomplete="given-name" required></div>
                        <div class="form-group"><label>نام خانوادگی</label><input name="customer_last_name" autocomplete="family-name"></div>
                    </div>
                    <div class="form-group"><label>شماره موبایل</label><input name="customer_mobile" inputmode="tel" autocomplete="tel" required pattern="[0-9+\-\s]{8,20}"></div>
                    <label class="check"><input type="checkbox" name="wants_reservation" value="1"> مایل هستم برای تماشای این مسابقه میز رزرو کنم</label>
                    <div class="actions"><button class="btn" type="submit">ثبت پیش‌بینی</button></div>
                </form>
            <?php endif; ?>
            <a class="back" href="/">بازگشت به سایت</a>
        </section>
    </section>
</main>
<script>
    (function () {
        const matchSelect = document.getElementById('match-select');
        const teamOne = document.getElementById('team-one-name');
        const teamTwo = document.getElementById('team-two-name');
        if (!matchSelect || !teamOne || !teamTwo) return;
        const updateTeams = () => {
            const option = matchSelect.options[matchSelect.selectedIndex];
            teamOne.textContent = option?.dataset?.teamOne || '-';
            teamTwo.textContent = option?.dataset?.teamTwo || '-';
        };
        matchSelect.addEventListener('change', updateTeams);
        updateTeams();
    }());
</script>
</body>
</html>
