<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/people.php';
require_once __DIR__ . '/includes/upload.php';
require_once __DIR__ . '/includes/nav.php';

$user = require_genealogy_auth();
$pdo = db();
$tableOk = genealogy_table_ok($pdo);

$id = (int) ($_GET['id'] ?? 0);
$person = $id ? genealogy_get_person($pdo, $id) : null;
$error = '';

if ($id && !$person) {
    header('Location: /painel/');
    exit;
}

$allPeople = $tableOk ? genealogy_search_people($pdo, '', $id ?: null, 500) : [];
$relations = $id ? genealogy_get_relations($pdo, $id) : ['father' => [], 'mother' => [], 'spouse' => [], 'child' => []];

$fatherId = $relations['father'][0]['id'] ?? '';
$motherId = $relations['mother'][0]['id'] ?? '';
$spouseIds = array_map(fn ($s) => $s['id'], $relations['spouse']);
$childIds = array_map(fn ($c) => $c['id'], $relations['child']);

if ($tableOk && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $savedId = genealogy_save_person($pdo, $_POST, $id ?: null);

        if (!empty($_POST['remove_photo'])) {
            genealogy_remove_person_photo($pdo, $savedId);
        } elseif (!empty($_FILES['photo']['name'])) {
            $photoPath = genealogy_store_photo($savedId, $_FILES['photo']);
            if ($photoPath) {
                genealogy_set_person_photo($pdo, $savedId, $photoPath);
            }
        }

        genealogy_save_relations_from_post($pdo, $savedId, $_POST);
        header('Location: /painel/?ok=saved');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $person = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'birth_date' => trim($_POST['birth_date'] ?? '') ?: null,
            'death_date' => trim($_POST['death_date'] ?? '') ?: null,
            'birth_year_only' => trim($_POST['birth_year_only'] ?? '') !== '' ? (int)$_POST['birth_year_only'] : null,
            'gender' => $_POST['gender'] ?? null,
            'notes' => trim($_POST['notes'] ?? '') ?: null,
            'photo_path' => is_array($person) ? ($person['photo_path'] ?? null) : null,
        ];
        $fatherId = (int) ($_POST['father_id'] ?? 0) ?: '';
        $motherId = (int) ($_POST['mother_id'] ?? 0) ?: '';
        $spouseIds = array_map('intval', (array) ($_POST['spouse_ids'] ?? []));
        $childIds = array_map('intval', (array) ($_POST['child_ids'] ?? []));
    }
}

$pageTitle = $id ? 'Editar pessoa' : 'Nova pessoa';
$photoUrl = genealogy_photo_url($person['photo_path'] ?? null);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"/>
  <meta name="theme-color" content="#1B3A2D"/>
  <title><?= genealogy_h($pageTitle) ?> — Árvore Genealógica</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <?php genealogy_painel_head(); ?>
</head>
<body class="painel-app">
<?php genealogy_painel_layout_start('pessoas', [
  'title' => $pageTitle,
  'subtitle' => $id ? genealogy_h($person['full_name'] ?? '') : 'Cadastre e vincule familiares',
  'user' => $user,
  'back_href' => '/painel/',
  'back_label' => 'Lista',
]); ?>

    <?php if (!$tableOk): ?>
      <div class="warn">Execute <code>admin/sql/11_genealogia.sql</code> no phpMyAdmin.</div>
    <?php else: ?>

      <?php if ($error): ?>
        <div class="warn"><?= genealogy_h($error) ?></div>
      <?php endif; ?>

      <form method="POST" id="person-form" enctype="multipart/form-data">

        <section class="form-section">
          <h2>Foto</h2>
          <p class="section-desc">Opcional. JPG, PNG, WEBP ou GIF — até 5 MB.</p>

          <div class="photo-field">
            <div class="photo-preview" id="photo-preview">
              <?php if ($photoUrl): ?>
                <img src="<?= genealogy_h($photoUrl) ?>" alt="Foto atual" class="photo-preview-img"/>
              <?php else: ?>
                <span class="photo-preview-placeholder" id="photo-placeholder">Sem foto</span>
              <?php endif; ?>
            </div>
            <div class="photo-actions">
              <label class="btn btn-secondary btn-sm photo-upload-btn">
                Escolher foto
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp,image/gif" hidden/>
              </label>
              <?php if ($photoUrl): ?>
              <label class="photo-remove">
                <input type="checkbox" name="remove_photo" value="1"/>
                Remover foto atual
              </label>
              <?php endif; ?>
            </div>
          </div>
        </section>

        <section class="form-section">
          <h2>Dados pessoais</h2>
          <p class="section-desc">Apenas o nome é obrigatório. Deixe em branco o que não souber.</p>

          <label class="field-label" for="full_name">Nome completo *</label>
          <input type="text" id="full_name" name="full_name" required
                 value="<?= genealogy_h($person['full_name'] ?? '') ?>" autocomplete="name"/>

          <div class="form-row form-row-2">
            <div>
              <label class="field-label" for="birth_date">Data de nascimento</label>
              <input type="date" id="birth_date" name="birth_date"
                     value="<?= genealogy_h($person['birth_date'] ?? '') ?>"/>
            </div>
            <div>
              <label class="field-label" for="death_date">Data de falecimento</label>
              <input type="date" id="death_date" name="death_date"
                     value="<?= genealogy_h($person['death_date'] ?? '') ?>"/>
            </div>
          </div>

          <label class="field-label" for="birth_year_only">Ou apenas o ano de nascimento</label>
          <input type="number" id="birth_year_only" name="birth_year_only" min="1800" max="2100" step="1"
                 placeholder="Ex: 1945"
                 value="<?= genealogy_h(isset($person['birth_year_only']) && $person['birth_year_only'] ? (string)$person['birth_year_only'] : '') ?>"/>
          <p class="field-hint">Use só se não souber o dia e mês. Se preencher a data completa, o ano é ignorado.</p>

          <label class="field-label" for="gender">Sexo</label>
          <select id="gender" name="gender">
            <option value="">— Não informado —</option>
            <option value="M"<?= ($person['gender'] ?? '') === 'M' ? ' selected' : '' ?>>Masculino</option>
            <option value="F"<?= ($person['gender'] ?? '') === 'F' ? ' selected' : '' ?>>Feminino</option>
            <option value="O"<?= ($person['gender'] ?? '') === 'O' ? ' selected' : '' ?>>Outro</option>
          </select>
          <p class="field-hint">Ajuda a vincular automaticamente como pai ou mãe ao adicionar filhos.</p>

          <label class="field-label" for="notes">Observações</label>
          <textarea id="notes" name="notes" placeholder="Histórico, profissão, cidade..."><?= genealogy_h($person['notes'] ?? '') ?></textarea>
        </section>

        <section class="form-section">
          <h2>Vínculos familiares</h2>
          <p class="section-desc">Selecione pessoas já cadastradas. Você pode completar depois.</p>

          <label class="field-label" for="father_id">Pai</label>
          <select id="father_id" name="father_id">
            <option value="">— Não definido —</option>
            <?php foreach ($allPeople as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= (string)$fatherId === (string)$p['id'] ? ' selected' : '' ?>>
              <?= genealogy_h($p['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>

          <label class="field-label" for="mother_id">Mãe</label>
          <select id="mother_id" name="mother_id">
            <option value="">— Não definida —</option>
            <?php foreach ($allPeople as $p): ?>
            <option value="<?= (int)$p['id'] ?>"<?= (string)$motherId === (string)$p['id'] ? ' selected' : '' ?>>
              <?= genealogy_h($p['full_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>

          <label class="field-label">Cônjuge(s)</label>
          <div class="picker-box" data-picker="spouse">
            <input type="search" class="picker-search" placeholder="Filtrar nomes..." data-filter/>
            <?php if (!$allPeople): ?>
              <p class="field-hint" style="padding:8px">Cadastre mais pessoas para vincular.</p>
            <?php else: ?>
              <?php foreach ($allPeople as $p): ?>
              <label class="picker-item" data-name="<?= genealogy_h(mb_strtolower($p['full_name'])) ?>">
                <input type="checkbox" name="spouse_ids[]" value="<?= (int)$p['id'] ?>"
                  <?= in_array((int)$p['id'], $spouseIds, true) ? ' checked' : '' ?>/>
                <span><?= genealogy_h($p['full_name']) ?></span>
              </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <label class="field-label">Filho(s)</label>
          <div class="picker-box" data-picker="child">
            <input type="search" class="picker-search" placeholder="Filtrar nomes..." data-filter/>
            <?php if (!$allPeople): ?>
              <p class="field-hint" style="padding:8px">Cadastre mais pessoas para vincular.</p>
            <?php else: ?>
              <?php foreach ($allPeople as $p): ?>
              <label class="picker-item" data-name="<?= genealogy_h(mb_strtolower($p['full_name'])) ?>">
                <input type="checkbox" name="child_ids[]" value="<?= (int)$p['id'] ?>"
                  <?= in_array((int)$p['id'], $childIds, true) ? ' checked' : '' ?>/>
                <span><?= genealogy_h($p['full_name']) ?></span>
              </label>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <p class="field-hint">Se o sexo estiver definido, o vínculo pai/mãe será criado automaticamente no filho.</p>
        </section>

        <div class="form-actions">
          <button type="submit" class="btn btn-primary">Salvar</button>
          <a href="/painel/" class="btn btn-secondary">Cancelar</a>
        </div>
      </form>

    <?php endif; ?>

<?php genealogy_painel_layout_end(); ?>
<script>
document.querySelectorAll('[data-filter]').forEach(function(input) {
  input.addEventListener('input', function() {
    var q = input.value.toLowerCase().trim();
    var box = input.closest('.picker-box');
    box.querySelectorAll('.picker-item').forEach(function(item) {
      var name = item.getAttribute('data-name') || '';
      item.style.display = !q || name.indexOf(q) !== -1 ? '' : 'none';
    });
  });
});

var photoInput = document.getElementById('photo');
if (photoInput) {
  photoInput.addEventListener('change', function() {
    var file = photoInput.files && photoInput.files[0];
    if (!file) return;
    var preview = document.getElementById('photo-preview');
    var img = preview.querySelector('.photo-preview-img');
    if (!img) {
      var ph = document.getElementById('photo-placeholder');
      if (ph) ph.remove();
      img = document.createElement('img');
      img.className = 'photo-preview-img';
      img.alt = 'Pré-visualização';
      preview.appendChild(img);
    }
    img.src = URL.createObjectURL(file);
    var removeCb = document.querySelector('input[name="remove_photo"]');
    if (removeCb) removeCb.checked = false;
  });
}
</script>
</body>
</html>
