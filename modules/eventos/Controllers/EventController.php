<?php

declare(strict_types=1);

namespace Modules\Eventos\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\View;
use Modules\Eventos\Models\EventModel;
use PDOException;
use Throwable;

final class EventController
{
    private const TIMEZONE = 'America/Sao_Paulo';
    private const TYPES = ['jogo', 'treino', 'amistoso', 'compromisso'];

    public function handle(array $config): void
    {
        header('Cache-Control: no-store, private');
        try {
            Session::start();
            $user = Auth::requireRole('admin');
            $model = new EventModel();
            $route = $_GET['route'] ?? (isset($_GET['id']) ? 'edit' : 'list');
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                    http_response_code(400);
                    exit('A sessão do formulário expirou. Atualize a página e tente novamente.');
                }
                $action = $_POST['action'] ?? '';
                if ($action === 'delete') {
                    $model->delete($this->id($_POST['id'] ?? null));
                    if ($this->expectsJson()) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['ok' => true, 'message' => 'Evento excluído.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $_SESSION['event_notice'] = ['message' => 'Evento excluído.', 'tone' => 'danger'];
                    session_write_close();
                    header('Location: eventos.php', true, 303);
                    exit;
                }
                if ($action !== 'save') {
                    http_response_code(400);
                    exit('Ação inválida.');
                }
                $idErrors = [];
                $eventId = $this->submittedId($_POST, $idErrors);
                $data = $this->validate($_POST);
                $errors = $data['_errors'];
                unset($data['_errors']);
                $errors = array_merge($idErrors, $errors);
                if (!$model->categoryExists($data['categoria_id'])) {
                    $errors[] = 'A categoria selecionada não está disponível.';
                }
                if (!$model->athleteExists($data['atleta_destaque_id'])) {
                    $errors[] = 'O atleta destaque selecionado não está disponível.';
                }
                if ($eventId !== null) $data['id'] = $eventId;
                $image = null;
                $removeImage = !$data['release_ativo'] || ($_POST['remover_imagem'] ?? '') === '1';
                if ($data['release_ativo'] && !$removeImage) {
                    try {
                        $image = $this->processImage($_FILES['release_imagem'] ?? null);
                    } catch (\InvalidArgumentException $exception) {
                        $errors[] = $exception->getMessage();
                    }
                }
                if ($errors !== []) {
                    $this->form($model, $config, $data, $errors);
                    return;
                }
                try {
                    if ($eventId === null) {
                        $model->create($data, (int) $user['id'], $image);
                    } else {
                        $model->update($eventId, $data, (int) $user['id'], $image, $removeImage);
                    }
                    $_SESSION['event_notice'] = [
                        'message' => $eventId !== null ? 'Evento alterado com sucesso.' : 'Evento adicionado com sucesso.',
                        'tone' => $eventId !== null ? 'warning' : 'success',
                    ];
                    session_write_close();
                    header('Location: eventos.php', true, 303);
                    exit;
                } catch (PDOException $exception) {
                    $state = (string) ($exception->errorInfo[0] ?? $exception->getCode());
                    error_log('Falha ao salvar evento: SQLSTATE ' . $state);
                    $this->form($model, $config, $data, ['Não foi possível salvar o evento. Confira os dados e tente novamente.']);
                    return;
                }
            }

            if ($route === 'edit') {
                $event = $model->find($this->id($_GET['id'] ?? null));
                if ($event === null) {
                    http_response_code(404);
                    exit('Evento não encontrado.');
                }
                $this->form($model, $config, $event, []);
                return;
            }
            if ($route === 'new') {
                $this->form($model, $config, ['tipo' => 'treino', 'status' => 'agendado'], []);
                return;
            }
            if ($route !== 'list') {
                http_response_code(404);
                exit('Página não encontrada.');
            }
            $escape = [View::class, 'escape'];
            $csrfToken = Csrf::token();
            $events = $model->all();
            $notice = $_SESSION['event_notice'] ?? null;
            unset($_SESSION['event_notice']);
            $categories = $model->categories();
            require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'index.php';
        } catch (Throwable $exception) {
            if ($this->expectsJson() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                $state = $exception instanceof PDOException
                    ? (string) ($exception->errorInfo[0] ?? $exception->getCode())
                    : '';
                $message = $state === '23503'
                    ? 'Não foi possível excluir o evento porque existem registros vinculados.'
                    : 'Não foi possível concluir a operação. Confira os dados e tente novamente.';
                http_response_code($state === '23503' ? 422 : 500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
                return;
            }
            error_log('Módulo de eventos indisponível: ' . get_class($exception));
            http_response_code(503);
            exit('O módulo de eventos está temporariamente indisponível.');
        }
    }

    private function expectsJson(): bool
    {
        return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    private function form(EventModel $model, array $config, array $values, array $errors): void
    {
        $escape = [View::class, 'escape'];
        $csrfToken = Csrf::token();
        $athletes = $model->athletes();
        $categories = $model->categories();
        require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR . 'form.php';
    }

    private function validate(array $input): array
    {
        $errors = [];
        $type = $input['tipo'] ?? '';
        if (!is_string($type) || !in_array($type, self::TYPES, true)) { $errors[] = 'Selecione um tipo de evento válido.'; $type = 'treino'; }
        $title = is_string($input['titulo'] ?? null) ? trim($input['titulo']) : '';
        if ($title === '' || mb_strlen($title, 'UTF-8') > 120) $errors[] = 'Informe um título de até 120 caracteres.';
        $opponent = is_string($input['adversario'] ?? null) ? trim($input['adversario']) : '';
        if (mb_strlen($opponent, 'UTF-8') > 120) $errors[] = 'O nome do adversário deve ter até 120 caracteres.';
        if (!in_array($type, ['jogo', 'amistoso'], true)) $opponent = '';
        $start = $this->dateTime($input['inicio'] ?? null, 'Informe uma data e hora válidas.', $errors, true);
        $end = $this->dateTime($input['fim'] ?? null, 'A data e hora de término são inválidas.', $errors, false);
        if ($start !== null && $end !== null && $end <= $start) $errors[] = 'O término deve ocorrer depois do início.';
        $location = is_string($input['local'] ?? null) ? trim($input['local']) : '';
        if (mb_strlen($location, 'UTF-8') > 180) $errors[] = 'O local deve ter até 180 caracteres.';
        $status = $input['status'] ?? 'agendado';
        if (!is_string($status) || !in_array($status, ['agendado', 'concluido', 'cancelado'], true)) { $errors[] = 'Selecione um status válido.'; $status = 'agendado'; }
        $scoreUs = $this->score($input['placar_nos'] ?? '', $errors);
        $scoreOpponent = $this->score($input['placar_adversario'] ?? '', $errors);
        $category = $input['categoria_id'] ?? '';
        if (!is_string($category) || ($category !== '' && preg_match('/\A[1-9]\d{0,17}\z/', $category) !== 1)) {
            $errors[] = 'Selecione uma categoria válida.';
            $category = '';
        }
        $highlight = $input['atleta_destaque_id'] ?? '';
        if (!is_string($highlight) || ($highlight !== '' && preg_match('/\A[1-9]\d{0,17}\z/', $highlight) !== 1)) { $errors[] = 'Selecione um atleta destaque válido.'; $highlight = ''; }
        if (!in_array($type, ['jogo', 'amistoso'], true)) { $scoreUs = null; $scoreOpponent = null; $highlight = ''; }
        $release = ($input['release_ativo'] ?? '') === '1';
        $releaseText = is_string($input['release_texto'] ?? null) ? trim($input['release_texto']) : '';
        if (mb_strlen($releaseText, 'UTF-8') > 12000) $errors[] = 'O release deve ter no máximo 12.000 caracteres.';
        if (!$release) $releaseText = '';
        return [
            'tipo' => $type, 'categoria_id' => $category === '' ? null : (int) $category,
            'titulo' => $title, 'adversario' => $opponent ?: null,
            'inicio' => $start, 'fim' => $end, 'local' => $location ?: null, 'status' => $status,
            'placar_nos' => $scoreUs, 'placar_adversario' => $scoreOpponent,
            'atleta_destaque_id' => $highlight === '' ? null : (int) $highlight,
            'release_ativo' => $release, 'release_texto' => $releaseText ?: null,
            '_errors' => $errors,
        ];
    }

    private function submittedId(array $input, array &$errors): ?int
    {
        if (!array_key_exists('id', $input)) return null;
        $rawId = $input['id'];
        if ((!is_string($rawId) && !is_int($rawId)) || preg_match('/\A[1-9]\d{0,17}\z/', (string) $rawId) !== 1) {
            $errors[] = 'Identificador do evento inválido.';
            return null;
        }
        return (int) $rawId;
    }

    private function dateTime(mixed $raw, string $error, array &$errors, bool $required): ?string
    {
        if (!is_string($raw) || trim($raw) === '') {
            if ($required) $errors[] = $error;
            return null;
        }
        $format = str_contains($raw, ':') && preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\z/', $raw) === 1
            ? 'Y-m-d\\TH:i:s'
            : 'Y-m-d\\TH:i';
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $raw, new \DateTimeZone(self::TIMEZONE));
        $issues = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($issues !== false && ($issues['warning_count'] > 0 || $issues['error_count'] > 0))
            || $date->format($format) !== $raw) {
            $errors[] = $error;
            return null;
        }
        return $date->format('Y-m-d H:i:sP');
    }

    private function score(mixed $raw, array &$errors): ?int
    {
        if ($raw === '') return null;
        if (!is_string($raw)) {
            $errors[] = 'O placar deve ser um número inteiro entre 0 e 999.';
            return null;
        }
        if (preg_match('/\A(?:0|[1-9]\d{0,2})\z/', $raw) !== 1) {
            $errors[] = 'O placar deve ser um número inteiro entre 0 e 999.';
            return null;
        }
        return (int) $raw;
    }

    private function processImage(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (!is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null)
            || !is_numeric($file['size'] ?? null) || $file['error'] !== UPLOAD_ERR_OK
            || !is_uploaded_file($file['tmp_name']) || (int) $file['size'] > 5_242_880) {
            throw new \InvalidArgumentException('A imagem do release deve ter no máximo 5 MB.');
        }
        $bytes = file_get_contents($file['tmp_name']);
        $info = is_string($bytes) ? @getimagesizefromstring($bytes) : false;
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || !function_exists('imagecreatefromstring')) {
            throw new \InvalidArgumentException('Envie uma imagem JPEG, PNG ou WebP.');
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > 3000 || $height > 3000 || $width * $height > 6_000_000) {
            throw new \InvalidArgumentException('A imagem excede as dimensões permitidas (3.000 × 3.000, até 6 MP).');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source) throw new \InvalidArgumentException('A imagem não pôde ser processada.');
        $scale = min(1, 1800 / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($target, 255, 255, 255);
        imagefill($target, 0, 0, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        ob_start();
        $encoded = imagejpeg($target, null, 82);
        $result = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);
        if (!$encoded || !is_string($result) || $result === '' || strlen($result) > 1_000_000) {
            throw new \InvalidArgumentException('A imagem otimizada não pode ultrapassar 1 MB.');
        }
        return base64_encode($result);
    }

    private function id(mixed $value): int
    {
        if (!is_string($value) && !is_int($value)) {
            http_response_code(400);
            exit('Identificador inválido.');
        }
        if (preg_match('/\A[1-9]\d{0,17}\z/', (string) $value) !== 1) {
            http_response_code(400);
            exit('Identificador inválido.');
        }
        return (int) $value;
    }
}
