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
            $legacyAction = $_GET['action'] ?? null;
            if ($_SERVER['REQUEST_METHOD'] === 'GET' && !array_key_exists('route', $_GET)
                && is_string($legacyAction)) {
                if (is_string($_GET['notice'] ?? null)) {
                    $_SESSION['athlete_notice'] = mb_substr($_GET['notice'], 0, 200, 'UTF-8');
                }
                $legacyDestination = match ($legacyAction) {
                    'new' => 'atletas-novo',
                    'categories' => 'atletas-categorias',
                    'edit' => 'editar-' . $this->createEditToken($this->id($_GET['id'] ?? null)),
                    'photo' => 'atleta-' . $this->id($_GET['id'] ?? null) . '-foto',
                    default => null,
                };
                if (is_string($legacyDestination)) {
                    header('Location: ' . $legacyDestination, true, 303);
                    exit;
                }
            }
            $actionValue = $_GET['route'] ?? 'list';
            $action = is_string($actionValue) ? $actionValue : '';
            $hasLegacyFilters = array_key_exists('q', $_GET)
                || array_key_exists('per_page', $_GET)
                || array_key_exists('page', $_GET)
                || in_array($_GET['status'] ?? null, ['ativos', 'todos'], true);
            $hasLegacyNotice = is_string($_GET['notice'] ?? null);
            if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET'
                && !array_key_exists('state', $_GET) && ($hasLegacyFilters || $hasLegacyNotice)) {
                if ($hasLegacyNotice) {
                    $_SESSION['athlete_notice'] = mb_substr($_GET['notice'], 0, 200, 'UTF-8');
                }
                if (!$hasLegacyFilters) {
                    header('Location: atletas', true, 303);
                    exit;
                }
                $legacySearch = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
                $legacyState = [
                    'q' => mb_substr($legacySearch, 0, 100, 'UTF-8'),
                    'status' => ($_GET['status'] ?? 'todos') === 'ativos' ? 'ativos' : 'todos',
                    'per_page' => in_array($_GET['per_page'] ?? null, ['10', '25', '50', '100'], true)
                        ? (string) $_GET['per_page'] : '10',
                    'page' => is_string($_GET['page'] ?? null)
                        && preg_match('/\A[1-9]\d{0,8}\z/', $_GET['page']) === 1 ? $_GET['page'] : '1',
                ];
                $legacyJson = json_encode($legacyState, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                $legacyToken = rtrim(strtr(base64_encode($legacyJson), '+/', '-_'), '=');
                header('Location: atletas-filtros-' . $legacyToken, true, 303);
                exit;
            }
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
                $athlete = null;
                if ($action === 'edit') {
                    $athleteId = $this->resolveEditToken($_GET['token'] ?? null);
                    if ($athleteId === null && array_key_exists('id', $_GET)) {
                        $token = $this->createEditToken($this->id($_GET['id']));
                        header('Location: editar-' . $token, true, 303);
                        exit;
                    }
                    if ($athleteId !== null) $athlete = $model->find($athleteId);
                }
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
            $query = $_GET;
            if (array_key_exists('state', $query)) {
                try {
                    $stateToken = $query['state'];
                    if (!is_string($stateToken) || strlen($stateToken) > 1024
                        || preg_match('/\A[A-Za-z0-9_-]+\z/', $stateToken) !== 1) {
                        throw new RuntimeException('Filtro invalido.');
                    }
                    $encodedState = strtr($stateToken, '-_', '+/');
                    $encodedState .= str_repeat('=', (4 - strlen($encodedState) % 4) % 4);
                    $jsonState = base64_decode($encodedState, true);
                    if (!is_string($jsonState)) throw new RuntimeException('Filtro invalido.');
                    $decodedState = json_decode($jsonState, false, 8, JSON_THROW_ON_ERROR);
                    if (!$decodedState instanceof \stdClass) throw new RuntimeException('Filtro invalido.');
                    $filterState = get_object_vars($decodedState);
                    if (array_diff(array_keys($filterState), ['q', 'status', 'per_page', 'page']) !== []) {
                        throw new RuntimeException('Filtro invalido.');
                    }
                    foreach ($filterState as $filterValue) {
                        if (!is_string($filterValue)) throw new RuntimeException('Filtro invalido.');
                    }
                    $query = array_replace($query, $filterState);
                } catch (Throwable) {
                    http_response_code(400);
                    exit('Filtro invalido.');
                }
            }
            $searchValue = $query['q'] ?? '';
            $search = is_string($searchValue) ? trim($searchValue) : '';
            $search = mb_substr($search, 0, 100, 'UTF-8');
            $activeOnly = ($query['status'] ?? 'todos') === 'ativos';
            $pageSizeValue = $query['per_page'] ?? '10';
            $pageSize = is_string($pageSizeValue) && in_array($pageSizeValue, ['10', '25', '50', '100'], true)
                ? (int) $pageSizeValue
                : 10;
            $pageValue = $query['page'] ?? '1';
            $page = is_string($pageValue) && preg_match('/\A[1-9]\d{0,8}\z/', $pageValue) === 1
                ? (int) $pageValue
                : 1;
            $totalAthletes = $model->count($search, $activeOnly);
            $totalPages = max(1, (int) ceil($totalAthletes / $pageSize));
            $page = min($page, $totalPages);
            $athletes = $model->list($search, $activeOnly, $pageSize, ($page - 1) * $pageSize);
            $athleteIds = array_column($athletes, 'id');
            $positionSummary = $model->positionSummary($athleteIds);
            $categorySummary = $model->categorySummary($athleteIds);
            $this->listing($config, $athletes, $search, $activeOnly, $pageSize, $page, $totalPages, $totalAthletes, $positionSummary, $categorySummary);
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
        if (in_array($action, ['create-category', 'add-position', 'add-role', 'update-category', 'update-position', 'update-role', 'delete-category', 'delete-position', 'delete-role'], true)) {
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
            if (str_starts_with($action, 'delete-')) {
                $type = substr($action, 7);
                $id = $this->id($_POST['id'] ?? null);
                match ($type) {
                    'category' => $model->deleteCategory($id),
                    'position' => $model->deletePosition($id),
                    'role' => $model->deleteRole($id),
                    default => throw new \InvalidArgumentException('Ação inválida.'),
                };
                $this->categoryActionResponse(true, 'Registro excluído.');
                $this->redirectCategories('Registro excluído.');
            }
            if ($action === 'create-category') {
                $modality = $_POST['modalidade'] ?? '';
                $gender = $_POST['genero'] ?? '';
                if (!is_string($modality) || preg_match('/\A[A-Za-z0-9][A-Za-z0-9 ._-]{0,39}\z/', trim($modality)) !== 1
                    || !is_string($gender) || !in_array($gender, ['masculino', 'feminino'], true)) {
                    throw new \InvalidArgumentException('Informe o nome da categoria e o gênero corretamente.');
                }
                if ($action === 'update-category') $model->updateCategory($this->id($_POST['id'] ?? null), trim($modality), $gender);
                else $model->addCategory(trim($modality), $gender);
            } elseif (in_array($action, ['add-position', 'update-position'], true)) {
                $categoryId = $this->id($_POST['categoria_id'] ?? null);
                $side = $_POST['side'] ?? '';
                $name = $_POST['nome_posicao'] ?? '';
                if (!is_string($side) || !in_array($side, ['ataque', 'defesa', 'time_especial'], true)
                    || !is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um side e uma posição válidos (até 60 caracteres).');
                }
                if ($action === 'update-position') $model->updatePosition($this->id($_POST['id'] ?? null), $categoryId, $side, trim($name));
                else $model->addPosition($categoryId, $side, trim($name));
            } else {
                $name = $_POST['nome_cargo'] ?? '';
                if (!is_string($name) || trim($name) === '' || mb_strlen(trim($name), 'UTF-8') > 60
                    || preg_match('/[\x00-\x1F\x7F]/u', $name) === 1) {
                    throw new \InvalidArgumentException('Informe um nome de cargo válido, com até 60 caracteres.');
                }
                if ($action === 'update-role') $model->updateRole($this->id($_POST['id'] ?? null), trim($name));
                else $model->addRole(trim($name));
            }
            $this->categoryActionResponse(true, 'Configuração salva.');
            $this->redirectCategories('Configuração salva.');
        } catch (PDOException $exception) {
            $state = (string) ($exception->errorInfo[0] ?? '');
            error_log('Falha ao configurar categorias de atletas: ' . $state);
            $message = $state === '23503'
                ? 'Não é possível excluir: este registro está vinculado a atletas ou a outros dados.'
                : ($state === '23505' && in_array($action, ['add-position', 'update-position'], true)
                    ? 'Essa posição já está cadastrada nessa categoria.'
                    : ($state === '23505' ? 'Já existe um registro com esses dados.' : 'Não foi possível salvar a configuração.'));
            $this->categoryActionResponse(false, $message);
            $this->categoriesPage($model, $config, $message);
        } catch (\InvalidArgumentException | RuntimeException $exception) {
            $this->categoryActionResponse(false, $exception->getMessage());
            $this->categoriesPage($model, $config, $exception->getMessage());
        }
    }

    private function categoryActionResponse(bool $ok, string $message): void
    {
        if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')) {
            http_response_code($ok ? 200 : 422);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
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
        ob_start();
        $encoded = function_exists('imagewebp')
            ? imagewebp($target, null, 82)
            : imagejpeg($target, null, 82);
        $result = ob_get_clean();
        imagedestroy($source); imagedestroy($target);
        if (!$encoded || !is_string($result) || $result === '') throw new \InvalidArgumentException('Image conversion failed.');
        if (!is_string($result) || strlen($result) > 512_000) throw new \InvalidArgumentException('A foto otimizada deve ter no máximo 500 KB.');
        return base64_encode($result);
    }

    private function photo(AthleteModel $model, int $id): void
    {
        $bytes = $model->photo($id);
        if ($bytes === null) { http_response_code(404); exit; }
        $imageInfo = @getimagesizefromstring($bytes);
        $mime = is_array($imageInfo) ? (string) ($imageInfo['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(415);
            exit;
        }
        header('Content-Type: ' . $mime); header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-cache, must-revalidate');
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

    private function listing(array $config, array $athletes, string $search, bool $activeOnly, int $pageSize, int $page, int $totalPages, int $totalAthletes, array $positionSummary, array $categorySummary): void
    {
        $escape = [View::class, 'escape']; $csrfToken = Csrf::token();
        foreach ($athletes as &$athlete) {
            $athlete['edit_token'] = $this->createEditToken((int) $athlete['id']);
        }
        unset($athlete);
        $notice = $_SESSION['athlete_notice'] ?? null;
        unset($_SESSION['athlete_notice']);
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'index.php';
    }

    private function categoriesPage(AthleteModel $model, array $config, ?string $error): void
    {
        $escape = [View::class, 'escape'];
        $csrfToken = Csrf::token();
        $notice = $_SESSION['athlete_notice'] ?? null;
        unset($_SESSION['athlete_notice']);
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
        $_SESSION['athlete_notice'] = $message;
        header('Location: atletas-categorias', true, 303);
        exit;
    }

    private function id(mixed $value): int
    {
        if (!is_scalar($value) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $value) !== 1) {
            http_response_code(400); exit('Identificador inválido.');
        }
        return (int) $value;
    }

    private function createEditToken(int $athleteId): string
    {
        $tokens = $_SESSION['athlete_edit_tokens'] ?? [];
        if (!is_array($tokens)) $tokens = [];
        $now = time();
        foreach ($tokens as $token => $entry) {
            if (!is_array($entry) || !is_int($entry['expires'] ?? null) || $entry['expires'] < $now) {
                unset($tokens[$token]);
            }
        }
        $token = bin2hex(random_bytes(16));
        $tokens[$token] = ['id' => $athleteId, 'expires' => $now + 3600];
        while (count($tokens) > 500) array_shift($tokens);
        $_SESSION['athlete_edit_tokens'] = $tokens;
        return $token;
    }

    private function resolveEditToken(mixed $token): ?int
    {
        if (!is_string($token) || preg_match('/\A[a-f0-9]{32}\z/', $token) !== 1) return null;
        $entry = $_SESSION['athlete_edit_tokens'][$token] ?? null;
        if (!is_array($entry) || !is_int($entry['id'] ?? null)
            || !is_int($entry['expires'] ?? null) || $entry['expires'] < time()) return null;
        return $entry['id'];
    }

    private function redirect(string $message): never
    {
        $_SESSION['athlete_notice'] = $message;
        header('Location: atletas', true, 303);
        exit;
    }
}
