<?php
/**
 * Admin Survey Builder - Dynamic Form Creator
 */

session_start();

require_once __DIR__ . '/../core/bootstrap.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/models/Survey.php';

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$currentAdmin = $auth->getCurrentAdmin();
$surveyModel = new Survey();

$formId = $_GET['id'] ?? null;
$editMode = $formId !== null;
$form = $editMode ? $surveyModel->getForm($formId) : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'form_name' => $_POST['form_name'] ?? '',
        'form_title_fa' => $_POST['form_title_fa'] ?? '',
        'form_title_en' => $_POST['form_title_en'] ?? '',
        'form_description_fa' => $_POST['form_description_fa'] ?? '',
        'form_description_en' => $_POST['form_description_en'] ?? '',
        'form_schema' => json_decode($_POST['form_schema'] ?? '{}', true),
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'display_order' => (int)($_POST['display_order'] ?? 0),
        'created_by' => $currentAdmin['id']
    ];
    
    if ($editMode) {
        $surveyModel->updateForm($formId, $formData);
        $success = 'فرم با موفقیت به‌روزرسانی شد';
    } else {
        $surveyModel->createForm($formData);
        $success = 'فرم با موفقیت ایجاد شد';
    }
    
    header('Location: surveys.php');
    exit;
}

$pageTitle = $editMode ? 'ویرایش فرم نظرسنجی' : 'ایجاد فرم نظرسنجی';
include __DIR__ . '/includes/header.php';
?>

<style>
    .builder-container {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 20px;
    }
    
    .form-editor {
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .field-list {
        margin-top: 30px;
    }
    
    .field-item {
        background: #f8f9fa;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 15px;
        position: relative;
    }
    
    .field-item.dragging {
        opacity: 0.5;
    }
    
    .field-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .field-type-badge {
        display: inline-block;
        padding: 4px 12px;
        background: var(--primary);
        color: white;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .field-actions {
        display: flex;
        gap: 10px;
    }
    
    .field-actions button {
        padding: 6px 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-edit {
        background: #17a2b8;
        color: white;
    }
    
    .btn-delete {
        background: #dc3545;
        color: white;
    }
    
    .drag-handle {
        cursor: move;
        color: #999;
        margin-left: 10px;
    }
    
    .toolbox {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        position: sticky;
        top: 20px;
    }
    
    .toolbox h3 {
        margin-bottom: 20px;
        color: var(--primary);
    }
    
    .field-type-btn {
        display: block;
        width: 100%;
        padding: 12px;
        margin-bottom: 10px;
        background: #f8f9fa;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        cursor: pointer;
        text-align: right;
        transition: all 0.3s;
    }
    
    .field-type-btn:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .field-type-btn .icon {
        margin-left: 10px;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        align-items: center;
        justify-content: center;
    }
    
    .modal.active {
        display: flex;
    }
    
    .modal-content {
        background: white;
        border-radius: 15px;
        padding: 30px;
        max-width: 600px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-close {
        font-size: 24px;
        cursor: pointer;
        color: #999;
    }
    
    .preview-section {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .preview-section h4 {
        margin-bottom: 15px;
        color: var(--primary);
    }
    
    .json-output {
        background: #2d2d2d;
        color: #f8f8f2;
        padding: 15px;
        border-radius: 8px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        max-height: 300px;
        overflow-y: auto;
        direction: ltr;
        text-align: left;
    }
</style>

<div class="builder-container">
    <div class="form-editor">
        <h2><?php echo $pageTitle; ?></h2>
        
        <form method="POST" id="mainForm">
            <div class="form-group">
                <label>نام فرم (انگلیسی)</label>
                <input type="text" name="form_name" class="form-control" 
                       value="<?php echo htmlspecialchars($form['form_name'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>عنوان فرم (فارسی)</label>
                <input type="text" name="form_title_fa" class="form-control" 
                       value="<?php echo htmlspecialchars($form['form_title_fa'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label>عنوان فرم (انگلیسی)</label>
                <input type="text" name="form_title_en" class="form-control" 
                       value="<?php echo htmlspecialchars($form['form_title_en'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label>توضیحات (فارسی)</label>
                <textarea name="form_description_fa" class="form-control" rows="3"><?php echo htmlspecialchars($form['form_description_fa'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="is_active" <?php echo ($form['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    فرم فعال است
                </label>
            </div>
            
            <div class="form-group">
                <label>ترتیب نمایش</label>
                <input type="number" name="display_order" class="form-control" 
                       value="<?php echo htmlspecialchars($form['display_order'] ?? 0); ?>">
            </div>
            
            <h3 class="mt-3">فیلدهای فرم</h3>
            <div class="field-list" id="fieldList">
                <!-- Fields will be added here dynamically -->
            </div>
            
            <input type="hidden" name="form_schema" id="formSchemaInput">
            
            <div class="preview-section">
                <h4>پیش‌نمایش JSON</h4>
                <pre class="json-output" id="jsonPreview">{}</pre>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block mt-3">ذخیره فرم</button>
        </form>
    </div>
    
    <div class="toolbox">
        <h3>افزودن فیلد</h3>
        
        <button class="field-type-btn" onclick="addField('stars')">
            <span class="icon">⭐</span>
            امتیاز ستاره‌ای
        </button>
        
        <button class="field-type-btn" onclick="addField('multiple_choice')">
            <span class="icon">☑️</span>
            چند گزینه‌ای
        </button>
        
        <button class="field-type-btn" onclick="addField('text')">
            <span class="icon">📝</span>
            متن کوتاه
        </button>
        
        <button class="field-type-btn" onclick="addField('textarea')">
            <span class="icon">📄</span>
            متن بلند
        </button>
        
        <button class="field-type-btn" onclick="addField('checkbox')">
            <span class="icon">✅</span>
            چک‌باکس
        </button>
    </div>
</div>

<!-- Field Editor Modal -->
<div class="modal" id="fieldModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">ویرایش فیلد</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div id="modalBody">
            <!-- Dynamic content -->
        </div>
    </div>
</div>

<script>
let formFields = <?php echo json_encode($form['form_schema']['fields'] ?? []); ?>;
let editingFieldIndex = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    renderFields();
    updateJSON();
});

function addField(type) {
    const field = {
        id: 'field_' + Date.now(),
        type: type,
        label_fa: '',
        label_en: '',
        required: false
    };
    
    // Type-specific defaults
    if (type === 'stars') {
        field.max_stars = 5;
    } else if (type === 'multiple_choice') {
        field.options = [];
    } else if (type === 'textarea' || type === 'text') {
        field.placeholder_fa = '';
        field.max_length = type === 'textarea' ? 500 : 100;
    }
    
    editingFieldIndex = formFields.length;
    formFields.push(field);
    
    openFieldEditor(editingFieldIndex);
}

function editField(index) {
    editingFieldIndex = index;
    openFieldEditor(index);
}

function deleteField(index) {
    if (confirm('آیا مطمئن هستید؟')) {
        formFields.splice(index, 1);
        renderFields();
        updateJSON();
    }
}

function openFieldEditor(index) {
    const field = formFields[index];
    const modal = document.getElementById('fieldModal');
    const modalBody = document.getElementById('modalBody');
    
    let html = `
        <div class="form-group">
            <label>شناسه فیلد (انگلیسی)</label>
            <input type="text" id="field_id" class="form-control" value="${field.id}" required>
        </div>
        
        <div class="form-group">
            <label>برچسب (فارسی)</label>
            <input type="text" id="field_label_fa" class="form-control" value="${field.label_fa || ''}" required>
        </div>
        
        <div class="form-group">
            <label>برچسب (انگلیسی)</label>
            <input type="text" id="field_label_en" class="form-control" value="${field.label_en || ''}">
        </div>
        
        <div class="form-group">
            <label>
                <input type="checkbox" id="field_required" ${field.required ? 'checked' : ''}>
                فیلد الزامی است
            </label>
        </div>
    `;
    
    // Type-specific fields
    if (field.type === 'stars') {
        html += `
            <div class="form-group">
                <label>تعداد ستاره</label>
                <input type="number" id="field_max_stars" class="form-control" value="${field.max_stars || 5}" min="1" max="10">
            </div>
        `;
    } else if (field.type === 'multiple_choice') {
        html += `
            <div class="form-group">
                <label>گزینه‌ها</label>
                <div id="optionsList"></div>
                <button type="button" class="btn btn-sm btn-primary" onclick="addOption()">افزودن گزینه</button>
            </div>
        `;
    } else if (field.type === 'textarea' || field.type === 'text') {
        html += `
            <div class="form-group">
                <label>متن راهنما (فارسی)</label>
                <input type="text" id="field_placeholder_fa" class="form-control" value="${field.placeholder_fa || ''}">
            </div>
            
            <div class="form-group">
                <label>حداکثر طول</label>
                <input type="number" id="field_max_length" class="form-control" value="${field.max_length || 100}">
            </div>
        `;
    }
    
    html += `
        <button type="button" class="btn btn-primary btn-block" onclick="saveField()">ذخیره فیلد</button>
    `;
    
    modalBody.innerHTML = html;
    
    // Render options if multiple choice
    if (field.type === 'multiple_choice') {
        renderOptions();
    }
    
    modal.classList.add('active');
}

function renderOptions() {
    const field = formFields[editingFieldIndex];
    const container = document.getElementById('optionsList');
    
    let html = '';
    (field.options || []).forEach((option, index) => {
        html += `
            <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                <input type="text" class="form-control" placeholder="مقدار" value="${option.value || ''}" onchange="updateOption(${index}, 'value', this.value)">
                <input type="text" class="form-control" placeholder="برچسب فارسی" value="${option.label_fa || ''}" onchange="updateOption(${index}, 'label_fa', this.value)">
                <button type="button" class="btn btn-sm btn-danger" onclick="removeOption(${index})">×</button>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function addOption() {
    const field = formFields[editingFieldIndex];
    if (!field.options) field.options = [];
    field.options.push({ value: '', label_fa: '', label_en: '' });
    renderOptions();
}

function updateOption(index, key, value) {
    const field = formFields[editingFieldIndex];
    field.options[index][key] = value;
}

function removeOption(index) {
    const field = formFields[editingFieldIndex];
    field.options.splice(index, 1);
    renderOptions();
}

function saveField() {
    const field = formFields[editingFieldIndex];
    
    field.id = document.getElementById('field_id').value;
    field.label_fa = document.getElementById('field_label_fa').value;
    field.label_en = document.getElementById('field_label_en').value;
    field.required = document.getElementById('field_required').checked;
    
    if (field.type === 'stars') {
        field.max_stars = parseInt(document.getElementById('field_max_stars').value);
    } else if (field.type === 'textarea' || field.type === 'text') {
        field.placeholder_fa = document.getElementById('field_placeholder_fa').value;
        field.max_length = parseInt(document.getElementById('field_max_length').value);
    }
    
    closeModal();
    renderFields();
    updateJSON();
}

function closeModal() {
    document.getElementById('fieldModal').classList.remove('active');
}

function renderFields() {
    const container = document.getElementById('fieldList');
    
    if (formFields.length === 0) {
        container.innerHTML = '<p class="text-muted">هیچ فیلدی اضافه نشده است. از جعبه ابزار استفاده کنید.</p>';
        return;
    }
    
    let html = '';
    formFields.forEach((field, index) => {
        html += `
            <div class="field-item" draggable="true">
                <div class="field-header">
                    <div>
                        <span class="drag-handle">☰</span>
                        <strong>${field.label_fa || 'بدون عنوان'}</strong>
                        <span class="field-type-badge">${getTypeLabel(field.type)}</span>
                        ${field.required ? '<span style="color: red;">*</span>' : ''}
                    </div>
                    <div class="field-actions">
                        <button class="btn-edit" onclick="editField(${index})">ویرایش</button>
                        <button class="btn-delete" onclick="deleteField(${index})">حذف</button>
                    </div>
                </div>
                <small style="color: #666;">ID: ${field.id}</small>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function getTypeLabel(type) {
    const labels = {
        'stars': 'ستاره',
        'multiple_choice': 'چند گزینه‌ای',
        'text': 'متن کوتاه',
        'textarea': 'متن بلند',
        'checkbox': 'چک‌باکس'
    };
    return labels[type] || type;
}

function updateJSON() {
    const schema = {
        fields: formFields
    };
    
    document.getElementById('formSchemaInput').value = JSON.stringify(schema);
    document.getElementById('jsonPreview').textContent = JSON.stringify(schema, null, 2);
}

// Form submission
document.getElementById('mainForm').addEventListener('submit', function(e) {
    updateJSON();
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
