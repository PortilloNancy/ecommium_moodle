<?php
// Encabezado
header('Content-Type: application/json');

// DEFINICIONES PARA EVITAR RENDER HTML
define('AJAX_SCRIPT', true);
define('NO_MOODLE_COOKIES', true);
define('NO_OUTPUT_BUFFERING', true);

try {
    $env = new EnvLoader(__DIR__ . '/.env');
    $validToken = $env->get('ACCESS_TOKEN', '');

    require_once(__DIR__ . '/../config.php');
    global $CFG;

    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->dirroot . '/completion/classes/progress.php');
    require_once($CFG->dirroot . '/grade/querylib.php');
    require_once($CFG->dirroot . '/user/lib.php');

} catch (Exception $e) {
    JsonResponse::error('Failed to initialize Moodle: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    JsonResponse::error('Failed to initialize Moodle: ' . $e->getMessage(), 500);
}

// ---------------- CLASES INTERNAS ----------------
// Carga el archivo .env
class EnvLoader {
    private array $vars = [];

    public function __construct(string $path) {
        $this->load($path);
    }

    private function load(string $path): void {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || substr($line, 0, 1) === '#' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $this->vars[trim($key)] = trim($value);
        }
    }

    public function get(string $key, $default = null) {
        return $this->vars[$key] ?? $default;
    }
}

// Validación del token
class TokenValidator {
    private string $validToken;
    public function __construct(string $token) {
        $this->validToken = $token;
    }
    public function validate(?string $token): bool {
        return $token === $this->validToken;
    }
}

// Manejo de respuestas
class JsonResponse {
    public static function success($data): void {
        self::send($data, 200);
    }

    public static function error(string $message, int $code = 400): void {
        self::send(['error' => $message], $code);
    }

    private static function send($data, int $status): void {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
}

// Resource de la data
class StudentSummaryResource {
    public static function format(stdClass $user, array $data): array {
        return [
            'userid' => $user->id,
            'fullname' => fullname($user),
            'lastaccess' => $data['lastaccess'],
            'completionpercentage' => $data['completionpercentage'],
            'activitiescompleted' => $data['activitiescompleted'],
            'finalgrade' => $data['finalgrade']
        ];
    }
}

// Service de la data
use core_completion\progress;

class StudentSummaryService {
    private int $courseid;
    private \context_course $context;
    private \stdClass $course;
    private array $users;
    private \completion_info $completion;

    public function __construct(int $courseid) {
        $this->courseid = $courseid;
        $this->loadData();
    }

    private function loadData(): void {
        global $DB;

        $this->context = \context_course::instance($this->courseid);
        $this->course = get_course($this->courseid);
        $this->users = get_enrolled_users($this->context);
        $this->completion = new \completion_info($this->course);
    }

    public function getSummaries(): array {
        global $DB;

        $data = [];
        $completion_enabled = $this->completion->is_enabled();
        
        foreach ($this->users as $user) {
            $userid = $user->id;

            // Cargar todos los datos del usuario permitidos ->*** ESTE MÉTODO REQUIERE LOGIN ***
            // $details = user_get_user_details($user, $this->course);

            // $fullname = $details['fullname'];
            // $lastaccessformatted = $details['lastaccess'] ?? null;
            // ***********************************************************************************


            $fullname = fullname($user);

            // Obtener último acceso al curso -> *** EL MÉTODO get_user_lastaccess() NO ESTÁ EN LA VERSION DE MOODLE UTILIZADA
            $lastaccessformatted = null;
            $lastaccessrecord = $DB->get_record('user_lastaccess', [
                'userid' => $userid,
                'courseid' => $this->courseid,
            ]);
            if ($lastaccessrecord) {
                $_data['lastaccessformatted'] = userdate($lastaccessrecord->timeaccess);
            }
            

            if ($completion_enabled) {
                try {
                    
                    // Obtener porcentaje de completion correctamente
                    $percentage = progress::get_course_progress_percentage($this->course, $userid); 
                    $_data['completionpercentage'] = is_null($percentage) ? null : round($percentage, 2);

                    // Contar actividades completadas
                    $_data['activitiescompleted'] = 0;
                    $modinfo = get_fast_modinfo($this->course, $userid);
                    foreach ($modinfo->get_cms() as $cm) {
                        if ($cm->uservisible && $this->completion->is_enabled($cm)) {
                            $completiondata = $this->completion->get_data($cm, false, $userid);
                            if ($completiondata && $completiondata->completionstate == COMPLETION_COMPLETE) {
                                $_data['activitiescompleted']++;
                            }
                        }
                    }
                } catch (Exception $e) {

                    JsonResponse::error('Failed to initialize Moodle: ' . $e->getMessage());


                }
            }

            // Obtener calificación final
            $grade = grade_get_course_grade($userid, $this->courseid);
            $_data['finalgrade'] = $grade->str_grade ?? null;

             $data[] = StudentSummaryResource::format($user, $_data);
        }

        return $data;
    }
}

// ---------------- PUNTO DE ENTRADA ----------------

// Validar token
$token = $_GET['token'] ?? '';
$validator = new TokenValidator($validToken);
if (!$validator->validate($token)) {
    JsonResponse::error('Invalid token', 401);
}

// Validar courseid
$courseid = isset($_GET['courseid']) ? (int)$_GET['courseid'] : 0;
if (!$courseid) {
    JsonResponse::error('Missing courseid', 400);
}

// Ejecutar servicio
try {
    $service = new StudentSummaryService($courseid);
    $data = $service->getSummaries();
    JsonResponse::success($data);
} catch (Exception $e) {
    JsonResponse::error('Error: ' . $e->getMessage(), 500);
}