<?php
require_once __DIR__ . '/core/models/MatchModel.php';
require_once __DIR__ . '/core/models/Prediction.php';
$matchModel = new MatchModel();
$predictionModel = new Prediction();
$matches = $matchModel->activeForPrediction();
$status = null; $message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCSRFToken($_POST[CSRF_TOKEN_NAME] ?? '')) throw new RuntimeException('درخواست نامعتبر است.');
        $predictionModel->createWithCrmMatch([
            'customer_name' => sanitizeInput($_POST['customer_name'] ?? ''),
            'mobile' => sanitizeInput($_POST['mobile'] ?? ''),
            'match_id' => (int)($_POST['match_id'] ?? 0),
            'predicted_score_team_a' => (int)($_POST['predicted_score_team_a'] ?? 0),
            'predicted_score_team_b' => (int)($_POST['predicted_score_team_b'] ?? 0),
        ]);
        $status = 'success'; $message = 'پیش‌بینی شما با موفقیت ثبت شد.';
    } catch (Throwable $e) { $status = 'error'; $message = $e->getMessage(); }
}
$token = generateCSRFToken();
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
        body{font-family:Vazirmatn,Tahoma,Arial;background:#f6f7f7;margin:0;color:#123}
        .wrap{max-width:720px;margin:40px auto;padding:20px}
        .card{background:#fff;border-radius:18px;padding:24px;box-shadow:0 10px 30px #0001}
        .form-group{margin-bottom:16px}
        label{display:block;margin-bottom:8px;font-weight:700}
        input,select{width:100%;padding:12px;border:1px solid #ddd;border-radius:10px}
        .btn{background:#004647;color:#fff;border:0;border-radius:10px;padding:12px 18px;cursor:pointer}
        .alert{padding:12px;border-radius:10px;margin-bottom:16px}
        .success{background:#d4edda}
        .error{background:#f8d7da}
        .scores{display:grid;grid-template-columns:1fr 1fr;gap:12px}
        .score-context{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:12px;margin:0 0 16px}
        .score-context p{margin:4px 0}
        @media(max-width:600px){.wrap{margin:10px}.scores{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <div class="card">
        <h1>پیش‌بینی جام جهانی</h1>
        <p>فقط مسابقاتی نمایش داده می‌شوند که بازه ثبت پیش‌بینی آن‌ها فعال است.</p>
        <?php if($status): ?><div class="alert <?=$status?>"><?=htmlspecialchars($message)?></div><?php endif; ?>
        <?php if(empty($matches)): ?>
            <p>در حال حاضر فرم پیش‌بینی فعالی وجود ندارد.</p>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="<?=CSRF_TOKEN_NAME?>" value="<?=htmlspecialchars($token)?>">
                <div class="form-group">
                    <label>مسابقه</label>
                    <select id="match-select" name="match_id" required>
                        <?php foreach($matches as $m): ?>
                            <option value="<?=$m['id']?>" data-team-a="<?=htmlspecialchars($m['team_a'])?>" data-team-b="<?=htmlspecialchars($m['team_b'])?>">
                                <?=htmlspecialchars($m['team_a'].' - '.$m['team_b'].' | '.formatJalaliDateTime($m['match_date'], false).' '.$m['kickoff_time'])?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="score-context" id="score-context">
                    <p id="team-a-label">گل تیم اول: -</p>
                    <p id="team-b-label">گل تیم دوم: -</p>
                </div>
                <div class="scores">
                    <div class="form-group">
                        <label id="score-label-a">گل تیم اول</label>
                        <input type="number" min="0" max="30" name="predicted_score_team_a" required>
                    </div>
                    <div class="form-group">
                        <label id="score-label-b">گل تیم دوم</label>
                        <input type="number" min="0" max="30" name="predicted_score_team_b" required>
                    </div>
                </div>
                <div class="form-group"><label>نام</label><input name="customer_name" required></div>
                <div class="form-group"><label>شماره تماس</label><input name="mobile" required pattern="[0-9+\-\s]{8,20}"></div>
                <button class="btn">ثبت پیش‌بینی</button>
            </form>
        <?php endif; ?>
        <p><a href="/">بازگشت به سایت</a></p>
    </div>
</main>
<script>
    (function () {
        const matchSelect = document.getElementById('match-select');
        if (!matchSelect) return;
        const teamALabel = document.getElementById('team-a-label');
        const teamBLabel = document.getElementById('team-b-label');
        const scoreLabelA = document.getElementById('score-label-a');
        const scoreLabelB = document.getElementById('score-label-b');
        const updateLabels = () => {
            const option = matchSelect.options[matchSelect.selectedIndex];
            const teamA = option?.dataset?.teamA || '-';
            const teamB = option?.dataset?.teamB || '-';
            teamALabel.textContent = 'گل تیم اول: ' + teamA;
            teamBLabel.textContent = 'گل تیم دوم: ' + teamB;
            scoreLabelA.textContent = 'گل تیم اول: ' + teamA;
            scoreLabelB.textContent = 'گل تیم دوم: ' + teamB;
        };
        matchSelect.addEventListener('change', updateLabels);
        updateLabels();
    }());
</script>
</body>
</html>
