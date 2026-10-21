<?php

declare(strict_types=1);

namespace Modules\Atletas\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Atletas\Models\AthleteModel;
use PDOException;
use RuntimeException;
use Throwable;

final class AthleteController
{
    private const MAX_UPLOAD = 5_242_880;
    private const BLOOD_TYPES = ['', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            $user = Auth::requireRole('admin');
            $model = new AthleteModel();
            $actionValue = $_GET['action'] ?? 'list';
            $action = is_string($actionValue) ? $actionValue : '';
            if ($action === 'photo') {
                $this->photo($model, $this->id($_GET['id'] ?? null));
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->post($model, (int) $user['id'], $config);
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                header('Allow: GET, POST');
                exit('Método não permitido.');
            }
            if ($action === 'categories') {
                $this->categoriesPage($model, $config, null);
                return;
            }
            if ($action === 'new' || $action === 'edit') {
                $athlete = $action === 'edit' ? $model->find($this->id($_GET['id'] ?? null)) : null;
                if ($action === 'edit' && $athlete === null) {
                    http_response_code(404);
                    exit('Cadastro não encontrado.');
                }
                $this->form($config, $athlete, [], null);
                return;
            }
            if ($action !== 'list') {
                http_response_code(404);
                exit('Página não encontrada.');
            }
            $searchValue = $_GET['q'] ?? '';
            $search = is_string($searchValue) ? trim($searchValue) : '';
            $search = mb_substr($search, 0, 100, 'UTF-8');
            $activeOnly = ($_GET['status'] ?? 'ativos') !== 'todos';
            $athletes = $model->list($search, $activeOnly, 100, 0);
            $athleteIds = array_column($athletes, 'id');
            $positionSummary = $model->positionSummary($athleteIds);
            $categorySummary = $model->categorySummary($athleteIds);
            $this->listing($config, $athletes, $search, $activeOnly, $positionSummary, $categorySummary);
        } catch (Throwable $exception) {
            if ($exception instanceof PDOException) {
                error_log('Falha de banco no módulo de atletas: ' . (string) ($exception->errorInfo[0] ?? 'indisponível'));
                http_response_code(503);
                exit('Não foi possível carregar o cadastro de atletas neste momento.');
            }
            error_log('Falha no módulo de atletas: ' . get_class($exception));
            http_response_code(503);
            exit('O módulo de atletas está temporariamente indisponível.');
        }
    }

    private function post(AthleteModel $model, int $userId, array $config): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(400);
            exit('A sessão do formulário expirou. Atualize a página e tente novamente.');
        }
        $actionValue = $_POST['action'] ?? 'save';
        $action = is_string($actionValue) ? $actionValue : '';
        if (in_array($action, ['create-category', 'add-position', 'add-role'], true)) {
            $this->saveCategoryConfiguration($model, $config, $action);
            return;
        }
        if ($action === 'toggle') {
            $model->toggle($this->id($_POST['id'] ?? null), $userId);
            $this->redirect('Cadastro atualizado.');
        }
        if ($action !== 'save') {
            http_response_code(400);
            exit('Ação inválida.');
        }
        $data = $this->validate($_POST);
        $errors = $data['_errors'];
        unset($data['_errors']);
        $id = (int) ($data['id'] ?? 0);
        $removePhoto = ($_POST['remover_foto'] ?? '') === '1';
        $photo = null;
        if ($removePhoto && isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Escolha remover a foto atual ou enviar uma nova, não as duas ações.';
        } elseif (!$removePhoto) {
            try {
                $photo = $this->processPhoto($_FILES['foto'] ?? null);
            } catch (\InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            $athlete = $id > 0 ? (new AthleteModel())->find($id) : null;
            $this->form($config, $athlete, $data, $errors);
            return;
        }
        try {
            $assignments = $data['categorias'];
            unset($data['categorias']);
            $model->save($data, $userId, $photo, $removePhoto, $assignments);
        } catch (PDOException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            if ($state === '23505') {
                $athlete = $id > 0 ? $model->find($id) : null;
                $constraint = (string) ($exception->errorInfo[2] ?? '');
                $message = match (true) {
                    str_contains($constraint, 'atletas_cpf_unique') => 'Este CPF já está cadastrado para outro atleta.',
                    str_contains($constraint, 'atletas_email_unique') => 'Este e-mail já está cadastrado para outro atleta.',
                    str_contains($constraint, 'atleta_categoria_numero_unique') => 'Este número de camisa já está ocupado nessa categoria. Escolha outro número disponível.',
                    default => 'Um dado que precisa ser único já está cadastrado. Confira o CPF, e-mail e números de camisa.',
                };
                $this->form($config, $athlete, $data + ['categorias' => $assignments], [$message]);
                return;
            }
            error_log('Falha ao salvar atleta: ' . $state);
            $athlete = $id > 0 ? $model->find($id) : null;
            $this->form($config, $athlete, $data + ['categorias' => $assignments], ['Não foi possível salvar os dados. Verifique o banco e tente novamente.']);
            return;
        } catch (RuntimeException $exception) {
            $athlete = $id > 0 ? $model->find($id) : null;
            $this->form($config, $athlete, $data + ['categorias' => $assignments], [$exception->getMessage()]);
            return;
        }
        $this->redirect('Cadastro salvo.');
    }

    private function validate(array $input): array
    {
        $errors = [];
        $text = static function (string $key, int $max, bool $required = false) use ($input, &$errors): ?string {
            $rawValue = $input[$key] ?? '';
            if (!is_string($rawValue) && !is_numeric($rawValue)) {
                $errors[] = 'Um dos campos contém um valor inválido.';
                return null;
            }
            $value = trim((string) $rawValue);
            if ($value === '') {
                if ($required) $errors[] = 'Nome é obrigatório.';
                return null;
            }
            if (mb_strlen($value, 'UTF-8') > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
                $errors[] = 'Um dos campos de texto ultrapassou o limite ou contém caracteres inválidos.';
                return null;
            }
            return $value;
        };
        $idValue = $input['id'] ?? '';
        if (!is_string($idValue) || ($idValue !== '' && $idValue !== '0' && preg_match('/\A[1-9]\d{0,17}\z/', $idValue) !== 1)) {
            $errors[] = 'Identificador inválido.';
            $id = 0;
        } else {
            $id = $idValue === '' || $idValue === '0' ? 0 : (int) $idValue;
        }
        $birthValue = $input['data_nascimento'] ?? '';
        $birth = is_string($birthValue) ? trim($birthValue) : '';
        if (!is_string($birthValue)) $errors[] = 'Data de nascimento inválida.';
        if ($birth !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $birth);
            if (!$date || $date->format('Y-m-d') !== $birth || $date > new \DateTimeImmutable('today')) $errors[] = 'Data de nascimento inválida.';
        } else $birth = null;
        $phone = static function (?string $value, bool $required = false) use (&$errors): ?string {
            if ($value === null) { if ($required) $errors[] = 'Informe o celular do contato de emergência.'; return null; }
            if (preg_match('/\A[0-9()+.\s-]{1,30}\z/', $value) !== 1) { $errors[] = 'Celular inválido.'; return null; }
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if (strlen($digits) < 10 || strlen($digits) > 13) { $errors[] = 'Celular deve conter entre 10 e 13 dígitos.'; return null; }
            return $digits;
        };
        $email = strtolower((string) ($text('email', 254) ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) $errors[] = 'E-mail inválido.';
        $cpfValue = $input['cpf'] ?? '';
        if (!is_string($cpfValue)) { $errors[] = 'CPF inválido.'; $cpfValue = ''; }
        $cpf = preg_replace('/\D+/', '', $cpfValue) ?? '';
        if ($cpf !== '' && !$this->validCpf($cpf)) $errors[] = 'CPF inválido.';
        $bloodValue = $input['tipo_sanguineo'] ?? '';
        $blood = is_string($bloodValue) ? $bloodValue : '';
        if (!in_array($blood, self::BLOOD_TYPES, true)) { $errors[] = 'Tipo sanguíneo inválido.'; $blood = ''; }
        $genderValue = $input['genero'] ?? '';
        $gender = is_string($genderValue) ? $genderValue : '';
        if (!in_array($gender, ['masculino', 'feminino'], true)) { $errors[] = 'Selecione o gênero esportivo do atleta.'; $gender = ''; }
        $assignments = $this->parseAssignments($input['categorias'] ?? [], $errors);
        $emergencyName = $text('contato_emergencia_nome', 120);
        $emergencyCell = $phone($text('contato_emergencia_celular', 30));
        $emergencyRelation = $text('contato_emergencia_parentesco', 60);
        return [
            'id' => $id, 'nome' => $text('nome', 120, true), 'apelido' => $text('apelido', 80),
            'genero' => $gender, 'data_nascimento' => $birth, 'celular' => $phone($text('celular', 30)),
            'rg' => $text('rg', 30), 'cpf' => $cpf === '' ? null : $cpf, 'email' => $email === '' ? null : $email,
            'contato_emergencia_nome' => $emergencyName, 'contato_emergencia_celular' => $emergencyCell,
            'contato_emergencia_parentesco' => $emergencyRelation, 'tipo_sanguineo' => $blood ?: null,
            'alergias' => $text('alergias', 2000), 'ativo' => ($_POST['ativo'] ?? '') === '1',
            'categorias' => $assignments, '_errors' => $errors,
        ];
    }

    private function parseAssignments(mixed $input, array &$errors): array
    {
        if (!is_array($input)) { $errors[] = 'Seleção de categorias inválida.'; return []; }
        $assignments = [];
        foreach ($input as $categoryId => $entry) {
            if (!is_string((string) $categoryId) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $categoryId) !== 1 || !is_array($entry)) {
                $errors[] = 'Uma categoria selecionada é inválida.';
                continue;
            }
            $included = ($entry['participa'] ?? '') === '1';
            $positions = $entry['posicoes'] ?? [];
            $roles = $entry['cargos'] ?? [];
            if (!is_array($positions) || !is_array($roles)) { $errors[] = 'Seleção de cargos ou posições inválida.'; continue; }
            $ids = [];
            foreach ($positions as $positionId) {
                if (!is_string($positionId) || preg_match('/\A[1-9]\d{0,17}\z/', $positionId) !== 1) {
                    $errors[] = 'Uma posição selecionada é inválida.';
                    continue;
                }
                $ids[] = (int) $positionId;
            }
            $roleIds = [];
            foreach ($roles as $roleId) {
                if (!is_string($roleId) || preg_match('/\A[1-9]\d{0,17}\z/', $roleId) !== 1) {
                    $errors[] = 'Um cargo selecionado é inválido.';
                    continue;
                }
                $roleIds[] = (int) $roleId;
            }
            $jerseyValue = $entry['numero_camisa'] ?? '';
            if (!is_string($jerseyValue) || ($jerseyValue !== '' && preg_match('/\A(?:0|[1-9]\d?)\z/', $jerseyValue) !== 1)) {
                $errors[] = 'Número de camisa inválido.';
                $jersey = null;
            } else {
                $jersey = $jerseyValue === '' ? null : (int) $jerseyValue;
            }
            if ($included || $ids !== [] || $roleIds !== [] || $jersey !== null) {
                $assignments[(int) $categoryId] = [
                    'numero_camisa' => $jersey,
                    'posicoes' => array_values(array_unique($ids)),
                    'cargos' => array_values(array_unique($roleIds)),
                ];
            }
        }
        return $assignments;
    }

    private function saveCategoryConfiguration(AthleteModel $model, array $config, string $action): void
    {
        try {
            if ($action === 'create-category') {
                $modality = $_POST['modalidade'] ?? '';
                $gender = $_POST['genero'] ?? '';
                if (!is_string($modality) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9 ._-]{0,39}\z/', trim($modality)) !== 1
                    || !is_string($gender) || !in_array($gender, ['masculino', 'feminino'], true)) {
                    throw new \InvalidArgumentException('Informe o nome da categoria e o gênero corretamente.');
                }
                $model->addCategory(trim($modality), $gender);
            } elseif ($action === 'add-position') {
                $categoryId = $this->id($_POST['categoria_id'] ?? null);
                $side = $_POST['side'] ?? '';
                $name = $_POST['nome_posicao'] ?? '';
                if (!is_string($side) || !in_array($side, ['ataque', 'defesa', 'time_especial'], true)
                    || !is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um side e uma posição válidos (até 60 caracteres).');
                }
                $model->addPosition($categoryId, $side, trim($name));
            } else {
                $name = $_POST['nome_cargo'] ?? '';
                if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um nome de cargo válido, com até 60 caracteres.');
                }
                $model->addRole(trim($name));
            }
            $this->redirectCategories('Configuração salva.');
        } catch (PDOException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            error_log('Falha ao configurar categorias de atletas: ' . $state);
            $message = $state === '23505' && $action === 'add-position'
                ? 'Essa posição já está cadastrada nessa categoria.'
                : 'Não foi possível salvar a configuração.';
            $this->categoriesPage($model, $config, $message);
        } catch (\InvalidArgumentException | RuntimeException $exception) {
            $this->categoriesPage($model, $config, $exception->getMessage());
        }
    }

    private function validCpf(string $cpf): bool
    {
        if (preg_match('/\A\d{11}\z/', $cpf) !== 1 || preg_match('/\A(\d)\1{10}\z/', $cpf) === 1) return false;
        for ($digit = 9; $digit < 11; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            $check = ($sum * 10) % 11;
            if ($check === 10) $check = 0;
            if ($check !== (int) $cpf[$digit]) return false;
        }
        return true;
    }

    private function processPhoto(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (!is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null) || !is_numeric($file['size'] ?? null)
            || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])
            || (int) $file['size'] > self::MAX_UPLOAD) throw new \InvalidArgumentException('A foto deve ter no máximo 5 MB.');
        $bytes = file_get_contents($file['tmp_name']);
        $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !function_exists('imagecreatefromstring')) {
            throw new \InvalidArgumentException('Envie uma imagem JPEG, PNG ou WebP.');
        }
        $width = (int) $info[0]; $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > 3000 || $height > 3000 || $width * $height > 6_000_000) {
            throw new \InvalidArgumentException('A imagem excede as dimensões permitidas (3.000 × 3.000, até 6 MP).');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) throw new \InvalidArgumentException('A imagem não pôde ser processada.');
        $scale = min(1, 1200 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale)); $newHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($target, 255, 255, 255); imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start(); imagejpeg($target, null, 82); $result = ob_get_clean();
        imagedestroy($source); imagedestroy($target);
        if (!is_string($result) || strlen($result) > 512_000) throw new \InvalidArgumentException('A foto otimizada deve ter no máximo 500 KB.');
        return base64_encode($result);
    }

    private function photo(AthleteModel $model, int $id): void
    {
        $bytes = $model->photo($id);
        if ($bytes === null) { http_response_code(404); exit; }
        header('Content-Type: image/jpeg'); header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($bytes)); echo $bytes;
    }

    private function form(array $config, ?array $athlete, array $values, ?array $errors): void
    {
        $escape = [View::class, 'escape']; $csrfToken = Csrf::token();
        $values = array_replace(is_array($athlete) ? $athlete : [], $values);
        $model = new AthleteModel();
        $categoryOptions = $this->groupCategories($model->categoryCatalog());
        $roles = $model->roleCatalog();
        $numberOptions = [];
        $athleteId = $athlete !== null ? (int) $athlete['id'] : null;
        foreach ($categoryOptions as $category) {
            $numberOptions[$category['id']] = $model->availableNumbers($category['id'], $athleteId);
        }
        if (is_array($values['categorias'] ?? null)) {
            $selectedCategories = $values['categorias'];
        } elseif ($athlete !== null) {
            $selectedCategories = $model->assignmentsForAthlete((int) $athlete['id']);
            foreach ($model->rolesForAthlete((int) $athlete['id']) as $categoryId => $roleIds) {
                $selectedCategories[$categoryId] ??= ['numero_camisa' => null, 'posicoes' => [], 'cargos' => []];
                $selectedCategories[$categoryId]['cargos'] = $roleIds;
            }
        } else {
            $selectedCategories = [];
        }
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
    }

    private function listing(array $config, array $athletes, string $search, bool $activeOnly, array $positionSummary, array $categorySummary): void
    {
        $escape = [View::class, 'escape']; $csrfToken = Csrf::token();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'index.php';
    }

    private function categoriesPage(AthleteModel $model, array $config, ?string $error): void
    {
        $escape = [View::class, 'escape'];
        $csrfToken = Csrf::token();
        $categoryOptions = $this->groupCategories($model->categoryCatalog());
        $roles = $model->roleCatalog();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'categories.php';
    }

    private function groupCategories(array $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $id = (int) $row['categoria_id'];
            $categories[$id] ??= [
                'id' => $id, 'modalidade' => (string) $row['modalidade'], 'genero' => (string) $row['genero'],
                'sides' => [],
            ];
            $sideCode = (string) $row['side_codigo'];
            $categories[$id]['sides'][$sideCode] ??= [
                'codigo' => $sideCode, 'nome' => (string) $row['side_nome'], 'posicoes' => [],
            ];
            if ($row['posicao_id'] !== null) {
                $categories[$id]['sides'][$sideCode]['posicoes'][] = [
                    'id' => (int) $row['posicao_id'], 'nome' => (string) $row['posicao_nome'],
                ];
            }
        }
        return array_values($categories);
    }

    private function redirectCategories(string $message): never
    {
        header('Location: atletas.php?action=categories&notice=' . rawurlencode($message), true, 303);
        exit;
    }

    private function id(mixed $value): int
    {
        if (!is_scalar($value) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $value) !== 1) {
            http_response_code(400); exit('Identificador inválido.');
        }
        return (int) $value;
    }

    private function redirect(string $message): never
    {
        header('Location: atletas.php?status=salvo&notice=' . rawurlencode($message), true, 303);
        exit;
    }
}
