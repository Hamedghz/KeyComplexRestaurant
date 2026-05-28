<?php
/**
 * Public Survey Page - Dynamic Form Renderer
 * Liquid Glass RTL Design
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/models/Survey.php';

$surveyModel = new Survey();
$form = $surveyModel->getActiveForm();

if (!$form) {
    die('هیچ نظرسنجی فعالی وجود ندارد');
}

$orderId = $_GET['order'] ?? null;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($form['form_title_fa']); ?> - KEY</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #004647;
            --accent: #D4AF37;
            --white: #FFFFFF;
            --black: #0A0A0A;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary) 0%, #002829 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            direction: rtl;
        }
        
        .survey-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .survey-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: var(--primary);
            font-weight: bold;
        }
        
        .survey-header h1 {
            color: var(--white);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .survey-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        
        .form-field {
            margin-bottom: 30px;
        }
        
        .field-label {
            display: block;
            color: var(--white);
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 16px;
        }
        
        .field-required {
            color: var(--accent);
            margin-right: 5px;
        }
        
        /* Star Rating */
        .star-rating {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .star {
            font-size: 40px;
            color: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            user-select: none;
        }
        
        .star:hover,
        .star.active {
            color: var(--accent);
            transform: scale(1.2);
        }
        
        /* Multiple Choice */
        .choice-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .choice-option {
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 15px 20px;
            cursor: pointer;
            transition: all 0.3s;
            color: var(--white);
        }
        
        .choice-option:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: var(--accent);
        }
        
        .choice-option.selected {
            background: rgba(212, 175, 55, 0.2);
            border-color: var(--accent);
        }
        
        .choice-option input[type="radio"] {
            margin-left: 10px;
        }
        
        /* Text Input */
        .text-input,
        .textarea-input {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            color: var(--white);
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .text-input:focus,
        .textarea-input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .textarea-input {
            resize: vertical;
            min-height: 120px;
            font-family: inherit;
        }
        
        .text-input::placeholder,
        .textarea-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Checkbox */
        .checkbox-field {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--white);
        }
        
        .checkbox-field input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        /* Submit Button */
        .submit-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--accent) 0%, #b8941f 100%);
            border: none;
            border-radius: 15px;
            color: var(--primary);
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.4);
        }
        
        .submit-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #d4edda;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #f8d7da;
        }
        
        /* Loading */
        .loading {
            display: none;
            text-align: center;
            color: var(--white);
            margin-top: 20px;
        }
        
        .loading.active {
            display: block;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--accent);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Success Screen */
        .success-screen {
            display: none;
            text-align: center;
        }
        
        .success-screen.active {
            display: block;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--accent);
            margin-bottom: 20px;
        }
        
        .success-screen h2 {
            color: var(--white);
            margin-bottom: 15px;
        }
        
        .success-screen p {
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .survey-container {
                padding: 30px 20px;
            }
            
            .star {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <div class="survey-container">
        <div class="survey-header">
            <div class="logo">K</div>
            <h1><?php echo htmlspecialchars($form['form_title_fa']); ?></h1>
            <?php if (!empty($form['form_description_fa'])): ?>
                <p><?php echo htmlspecialchars($form['form_description_fa']); ?></p>
            <?php endif; ?>
        </div>
        
        <div id="alertContainer"></div>
        
        <form id="surveyForm">
            <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
            <?php if ($orderId): ?>
                <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId); ?>">
            <?php endif; ?>
            
            <?php foreach ($form['form_schema']['fields'] as $field): ?>
                <div class="form-field" data-field-id="<?php echo htmlspecialchars($field['id']); ?>">
                    <label class="field-label">
                        <?php if ($field['required']): ?>
                            <span class="field-required">*</span>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($field['label_fa']); ?>
                    </label>
                    
                    <?php if ($field['type'] === 'stars'): ?>
                        <div class="star-rating" data-field="<?php echo htmlspecialchars($field['id']); ?>">
                            <?php for ($i = 1; $i <= ($field['max_stars'] ?? 5); $i++): ?>
                                <span class="star" data-value="<?php echo $i; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="<?php echo htmlspecialchars($field['id']); ?>" <?php echo $field['required'] ? 'required' : ''; ?>>
                        
                    <?php elseif ($field['type'] === 'multiple_choice'): ?>
                        <div class="choice-options">
                            <?php foreach ($field['options'] as $option): ?>
                                <label class="choice-option">
                                    <input type="radio" 
                                           name="<?php echo htmlspecialchars($field['id']); ?>" 
                                           value="<?php echo htmlspecialchars($option['value']); ?>"
                                           <?php echo $field['required'] ? 'required' : ''; ?>>
                                    <?php echo htmlspecialchars($option['label_fa']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                    <?php elseif ($field['type'] === 'textarea'): ?>
                        <textarea class="textarea-input" 
                                  name="<?php echo htmlspecialchars($field['id']); ?>"
                                  placeholder="<?php echo htmlspecialchars($field['placeholder_fa'] ?? ''); ?>"
                                  <?php echo $field['required'] ? 'required' : ''; ?>
                                  <?php echo isset($field['max_length']) ? 'maxlength="' . $field['max_length'] . '"' : ''; ?>></textarea>
                        
                    <?php elseif ($field['type'] === 'text'): ?>
                        <input type="text" 
                               class="text-input" 
                               name="<?php echo htmlspecialchars($field['id']); ?>"
                               placeholder="<?php echo htmlspecialchars($field['placeholder_fa'] ?? ''); ?>"
                               <?php echo $field['required'] ? 'required' : ''; ?>
                               <?php echo isset($field['max_length']) ? 'maxlength="' . $field['max_length'] . '"' : ''; ?>>
                        
                    <?php elseif ($field['type'] === 'checkbox'): ?>
                        <div class="checkbox-field">
                            <input type="checkbox" 
                                   name="<?php echo htmlspecialchars($field['id']); ?>" 
                                   value="1"
                                   <?php echo $field['required'] ? 'required' : ''; ?>>
                            <span><?php echo htmlspecialchars($field['label_fa']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            
            <button type="submit" class="submit-button">ارسال نظرسنجی</button>
        </form>
        
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>در حال ارسال...</p>
        </div>
        
        <div class="success-screen" id="successScreen">
            <div class="success-icon">✓</div>
            <h2>با تشکر از شما!</h2>
            <p>نظر شما با موفقیت ثبت شد.</p>
        </div>
    </div>
    
    <script>
        // Star rating functionality
        document.querySelectorAll('.star-rating').forEach(rating => {
            const stars = rating.querySelectorAll('.star');
            const fieldName = rating.dataset.field;
            const hiddenInput = document.querySelector(`input[name="${fieldName}"]`);
            
            stars.forEach((star, index) => {
                star.addEventListener('click', () => {
                    const value = star.dataset.value;
                    hiddenInput.value = value;
                    
                    // Update visual state
                    stars.forEach((s, i) => {
                        if (i < index + 1) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
                
                star.addEventListener('mouseenter', () => {
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.style.color = 'var(--accent)';
                        } else {
                            s.style.color = 'rgba(255, 255, 255, 0.3)';
                        }
                    });
                });
            });
            
            rating.addEventListener('mouseleave', () => {
                const currentValue = parseInt(hiddenInput.value) || 0;
                stars.forEach((s, i) => {
                    if (i < currentValue) {
                        s.style.color = 'var(--accent)';
                    } else {
                        s.style.color = 'rgba(255, 255, 255, 0.3)';
                    }
                });
            });
        });
        
        // Multiple choice selection visual feedback
        document.querySelectorAll('.choice-option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Remove selected class from siblings
                this.parentElement.querySelectorAll('.choice-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to this option
                this.classList.add('selected');
            });
        });
        
        // Form submission
        document.getElementById('surveyForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {};
            
            formData.forEach((value, key) => {
                if (key !== 'form_id' && key !== 'order_id') {
                    data[key] = value;
                }
            });
            
            // Show loading
            document.getElementById('surveyForm').style.display = 'none';
            document.getElementById('loading').classList.add('active');
            
            try {
                const response = await fetch('api/survey-submit.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        form_id: formData.get('form_id'),
                        order_id: formData.get('order_id'),
                        response_data: data
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success screen
                    document.getElementById('loading').classList.remove('active');
                    document.getElementById('successScreen').classList.add('active');
                } else {
                    throw new Error(result.message || 'خطا در ارسال');
                }
                
            } catch (error) {
                // Show error
                document.getElementById('loading').classList.remove('active');
                document.getElementById('surveyForm').style.display = 'block';
                
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.textContent = error.message || 'خطا در ارسال نظرسنجی';
                
                const container = document.getElementById('alertContainer');
                container.innerHTML = '';
                container.appendChild(alertDiv);
            }
        });
    </script>
</body>
</html>
