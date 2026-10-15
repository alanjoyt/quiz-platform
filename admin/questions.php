<?php
$pageTitle = "Manage Questions";
require_once __DIR__ . '/../auth_helper.php';
require_once __DIR__ . '/../db.php';

// Require admin
$adminUser = requireAdmin();

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? trim($_POST['action']) : '';
    
    if ($action === 'add') {
        $text = isset($_POST['text']) ? trim($_POST['text']) : '';
        $optionA = isset($_POST['option_a']) ? trim($_POST['option_a']) : '';
        $optionB = isset($_POST['option_b']) ? trim($_POST['option_b']) : '';
        $optionC = isset($_POST['option_c']) ? trim($_POST['option_c']) : '';
        $optionD = isset($_POST['option_d']) ? trim($_POST['option_d']) : '';
        $correct = isset($_POST['correct_option']) ? strtoupper(trim($_POST['correct_option'])) : 'A';
        $points = isset($_POST['points']) ? intval($_POST['points']) : 10;
        
        if (empty($text) || empty($optionA) || empty($optionB) || empty($optionC) || empty($optionD) || empty($correct)) {
            setFlashMessage("All fields are required to create a question.", "error");
        } else {
            $insert = $pdo->prepare("INSERT INTO questions (text, option_a, option_b, option_c, option_d, correct_option, points) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$text, $optionA, $optionB, $optionC, $optionD, $correct, $points]);
            setFlashMessage("Question added successfully.", "success");
        }
        
    } elseif ($action === 'edit') {
        $qId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $text = isset($_POST['text']) ? trim($_POST['text']) : '';
        $optionA = isset($_POST['option_a']) ? trim($_POST['option_a']) : '';
        $optionB = isset($_POST['option_b']) ? trim($_POST['option_b']) : '';
        $optionC = isset($_POST['option_c']) ? trim($_POST['option_c']) : '';
        $optionD = isset($_POST['option_d']) ? trim($_POST['option_d']) : '';
        $correct = isset($_POST['correct_option']) ? strtoupper(trim($_POST['correct_option'])) : 'A';
        $points = isset($_POST['points']) ? intval($_POST['points']) : 10;
        
        $update = $pdo->prepare("UPDATE questions SET text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, points = ? WHERE id = ?");
        $update->execute([$text, $optionA, $optionB, $optionC, $optionD, $correct, $points, $qId]);
        setFlashMessage("Question updated successfully.", "success");
        
    } elseif ($action === 'delete') {
        $qId = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $delete = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $delete->execute([$qId]);
        setFlashMessage("Question deleted successfully.", "success");
    }
    
    header("Location: " . BASE_URI . "admin/questions");
    exit;
}

// Fetch questions
$questions = $pdo->query("SELECT * FROM questions ORDER BY id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="margin-top: 1rem;">
    <h2 class="card-title" style="border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 1rem;">
        Question Management
    </h2>

    <!-- Admin Navigation Links -->
    <div class="admin-nav">
        <a href="<?php echo BASE_URI; ?>admin/dashboard">Overview & Config</a>
        <a href="<?php echo BASE_URI; ?>admin/questions" class="active">Questions CRUD</a>
        <a href="<?php echo BASE_URI; ?>admin/users">Participants</a>
        <a href="<?php echo BASE_URI; ?>admin/export" style="color: var(--primary-accent);">📥 Export CSV</a>
    </div>

    <!-- Add Question Form -->
    <details class="card" style="margin-bottom: 2rem; border-color: var(--primary-accent); padding: 1rem;" open>
        <summary style="font-weight: 700; cursor: pointer; color: var(--primary-accent); outline: none; font-family: var(--font-heading);">
            ➕ Create New Question
        </summary>
        
        <form method="POST" action="<?php echo BASE_URI; ?>admin/questions" style="margin-top: 1rem;">
            <input type="hidden" name="action" value="add">
            
            <div class="form-group">
                <label for="new_text" class="form-label">Question Text</label>
                <textarea id="new_text" name="text" class="form-control" rows="3" placeholder="Enter question..." required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label for="new_a" class="form-label">Option A</label>
                    <input type="text" id="new_a" name="option_a" class="form-control" placeholder="Option A" required>
                </div>
                <div class="form-group">
                    <label for="new_b" class="form-label">Option B</label>
                    <input type="text" id="new_b" name="option_b" class="form-control" placeholder="Option B" required>
                </div>
                <div class="form-group">
                    <label for="new_c" class="form-label">Option C</label>
                    <input type="text" id="new_c" name="option_c" class="form-control" placeholder="Option C" required>
                </div>
                <div class="form-group">
                    <label for="new_d" class="form-label">Option D</label>
                    <input type="text" id="new_d" name="option_d" class="form-control" placeholder="Option D" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label for="new_correct" class="form-label">Correct Option</label>
                    <select id="new_correct" name="correct_option" class="form-control" required>
                        <option value="A">A</option>
                        <option value="B">B</option>
                        <option value="C">C</option>
                        <option value="D">D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_points" class="form-label">Base Points</label>
                    <input type="number" id="new_points" name="points" class="form-control" value="10" min="1" required>
                </div>
            </div>

            <button type="submit" class="btn btn-secondary">
                Create Question
            </button>
        </form>
    </details>

    <!-- Questions list -->
    <h3 class="card-title" style="margin-bottom: 1rem;">Existing Questions (<?php echo count($questions); ?>)</h3>
    
    <?php if (count($questions) > 0): ?>
        <?php foreach ($questions as $q): ?>
            <div class="card" style="padding: 1rem; border-color: var(--border-color); background-color: var(--bg-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; margin-bottom: 0.75rem;">
                    <strong style="color: var(--primary-accent);">Question ID: <?php echo $q['id']; ?></strong>
                    <span style="font-size: 0.8rem; font-weight: 700; background-color: rgba(15, 82, 87, 0.1); color: var(--secondary-accent); padding: 0.1rem 0.5rem; border-radius: 4px;">
                        <?php echo $q['points']; ?> Points
                    </span>
                </div>
                
                <!-- Inline edit form -->
                <form method="POST" action="<?php echo BASE_URI; ?>admin/questions">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                    
                    <div class="form-group">
                        <input type="text" name="text" class="form-control" value="<?php echo htmlspecialchars($q['text']); ?>" style="font-weight: 600;" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem; margin-bottom: 0.75rem;">
                        <div>
                            <span style="font-weight: 700; color: var(--text-muted);">A:</span>
                            <input type="text" name="option_a" class="form-control" value="<?php echo htmlspecialchars($q['option_a']); ?>" required>
                        </div>
                        <div>
                            <span style="font-weight: 700; color: var(--text-muted);">B:</span>
                            <input type="text" name="option_b" class="form-control" value="<?php echo htmlspecialchars($q['option_b']); ?>" required>
                        </div>
                        <div>
                            <span style="font-weight: 700; color: var(--text-muted);">C:</span>
                            <input type="text" name="option_c" class="form-control" value="<?php echo htmlspecialchars($q['option_c']); ?>" required>
                        </div>
                        <div>
                            <span style="font-weight: 700; color: var(--text-muted);">D:</span>
                            <input type="text" name="option_d" class="form-control" value="<?php echo htmlspecialchars($q['option_d']); ?>" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Correct Option</label>
                            <select name="correct_option" class="form-control" required>
                                <option value="A" <?php echo $q['correct_option'] === 'A' ? 'selected' : ''; ?>>A</option>
                                <option value="B" <?php echo $q['correct_option'] === 'B' ? 'selected' : ''; ?>>B</option>
                                <option value="C" <?php echo $q['correct_option'] === 'C' ? 'selected' : ''; ?>>C</option>
                                <option value="D" <?php echo $q['correct_option'] === 'D' ? 'selected' : ''; ?>>D</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem;">Base Points</label>
                            <input type="number" name="points" class="form-control" value="<?php echo $q['points']; ?>" min="1" required>
                        </div>
                    </div>

                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 1rem;">
                        <button type="submit" class="btn btn-secondary" style="min-height: 38px; width: auto; font-size: 0.85rem; padding: 0.25rem 1rem;">
                            Update
                        </button>
                    </div>
                </form>
                
                <!-- Separate delete form -->
                <form method="POST" action="<?php echo BASE_URI; ?>admin/questions" onsubmit="return confirm('Are you sure you want to delete Question ID <?php echo $q['id']; ?>?');" style="margin-top: -38px; width: fit-content;">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo $q['id']; ?>">
                    <button type="submit" class="btn btn-danger" style="min-height: 38px; font-size: 0.85rem; padding: 0.25rem 1rem;">
                        Delete
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: var(--text-muted); font-size: 0.9rem; padding: 2rem 0;">
            No questions exist in database. Seed sample questions or create some above.
        </p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
