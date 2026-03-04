<?php

/**
 * @OA\Tag(
 *     name="Activos",
 *     description="Activos de 11Cert.",
 * )
 * @OA\Tag(
 *     name="Vistas",
 *     description="Redirección a alguna página de la herramienta.",
 * )
 * @OA\Tag(
 *     name="Autenticación",
 *     description="Autenticación de usuarios de la herramienta.",
 * )
 * @OA\Tag(
 *     name="Evaluaciones",
 *     description="Evaluaciones de riesgos de activos.",
 * )
 * @OA\Tag(
 *     name="Kpms",
 *     description="Kpms.",
 * )
 * @OA\Tag(
 *     name="Usuarios",
 *     description="Usuarios de 11Cert",
 * )
 * @OA\Tag(
 *     name="Evaluación / EVS",
 *     description="Gestión del módulo de Pentest.",
 * )
 * @OA\Tag(
 *     name="Evaluación / EVS / SDLC",
 *     description="SDLC en el módulo de Pentest.",
 * )
 * @OA\Tag(
 *     name="Evaluación / EVS / Jira",
 *     description="Interacciones con Jira en el módulo de Pentest.",
 * )
 * @OA\Tag(
 *     name="Evaluación / PAC",
 *     description="Gestión del Plan de Acciones Correctivas.",
 * )
 * @OA\Tag(
 *     name="Evaluación / EAS",
 *     description="Gestión del módulo de Arquitectura.",
 * )
 * @OA\Tag(
 *     name="Evaluación / EAE",
 *     description="Gestión de la Evaluación de Arquitectura Empresarial.",
 * )
 * @OA\Tag(
 *     name="Evaluación / ERS",
 *     description="Gestión de la Evaluación de Riesgos de Seguridad.",
 * )
 * @OA\Tag(
 *     name="Evaluación / ECR",
 *     description="Gestión de la Evaluación de Cumplimiento Regulatorio.",
 * )
 * @OA\Info(
 *     version="1.0",
 *     title="API 11CertTool",
 *     description="Documentación de la API de 11CertTool",
 *     @OA\Contact(name="Area CISO Telefonica")
 * )
 * @OA\Server(
 *     url="http://localhost:8080",
 *     description="Endpoint de 11Cert"
 * )
 */
date_default_timezone_set('Europe/Madrid');

use Psr\Http\Message\ResponseInterface as Response; // Respuesta
use Psr\Http\Message\ServerRequestInterface as Request; // Petición
use Slim\Factory\AppFactory; // Slim APP
use Selective\BasePath\BasePathDetector; // Modulo para rutas cogidas de configuración apache
use PhpOffice\PhpSpreadsheet\Spreadsheet; // Modulo para lectura excel
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\Element\Chart;
use myPHPnotes\Microsoft\Auth;
use OpenApi\Annotations as OA;

const TEMPLATE_DIR = './templates/';
const FECHA = 'fecha';
const DB_NEW = 'octopus_new';
const DB_SERV = 'octopus_serv';
const DB_KPMS = 'octopus_kpms';
const DB_USER = 'octopus_users';
const DB_CACHE = 'octopus_cache';
const ERROR = 'error';
const TOTAL = 'total';
const NORMATIVA = 'normativa';
const UPLOADS = 'uploads';
const CONTENT_TYPE = 'Content-type';
const JSON = 'application/json';
const NOMBRE = 'nombre';
const HEADER = 'X-Token';
const MESSAGE = 'message';
const PREGUNTAS = 'preguntas';
const ACTIVO = 'activo';
const CLASE_ACTIVOS = 'claseActivos';
const NAMETOOL = '11CertTool';
const TOKEN = 'userID';
const AMENAZAS = 'amenazas';
const SECRET_KEY = 'modifytoscript';
const MENSAJE_ACTIVOS_OBTENIDOS = 'Activos obtenidos correctamente';


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/operationsDB.php';
require_once __DIR__ . '/../App/Middleware/TokenMiddleware.php';
require_once __DIR__ . '/../App/Middleware/PermissionsMiddleware.php';

$app = AppFactory::create();
$basePath = (new BasePathDetector($_SERVER))->getBasePath();
$app->setBasePath($basePath);
require_once '../includes/functions.php';
require_once '../includes/email.php';
require_once '../includes/token.php';
require_once '../includes/JIRA.php';
require_once '../includes/generadorDocumento.php';
require_once '../includes/prisma.php';
require_once '../includes/Kiuwan.php';

const ERRORRESPONDER = new ErrorResponder();

$args = "";
$response = "";
$request = "";
$handler = "";
$_ = "";
$_request = "";
$_response = "";

// Registra el middleware en la aplicación Slim
$app->add(new PermissionsMiddleware($basePath));
$app->add(new TokenMiddleware($basePath));

$app->add(function ($request, $handler) {
	$response = $handler->handle($request);
	$origin = $request->getHeaderLine('Origin');
	$allowedOrigins = ['', 'http://localhost:8080', 'http://localhost', 'https://11certools.cisocdo.com', 'https://pre-11cert.westeurope.cloudapp.azure.com'];

	if (in_array($origin, $allowedOrigins)) {
		// Establecer las cabeceras CORS para el dominio permitido
		$response = $response
			->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
			->withHeader('Access-Control-Allow-Methods', 'GET, POST');
	} else {
		// Devolver una respuesta de error para las solicitudes de dominios no permitidos
		$corsResponse = new \Slim\Psr7\Response();
		$corsResponse->getBody()->write("No permitido. Origen: $origin");
		$corsResponse = $corsResponse->withStatus(403);
		return $corsResponse;
	}
	return $response;
});

class RouteException extends Exception {}

// BLOQUE DE GET URLS
/**
 * @OA\Get(
 *     path="/",
 *     tags={"Vistas"},
 *     summary="Si la respuesta es exitosa, redirige a la página de Login.",
 *     @OA\Response(response="200", description="Si la respuesta es exitosa, redirige a la página de Login."),
 * )
 */
$app->get('/', function (Request $_, Response $response) {
	return $response->withHeader('Location', 'login')->withStatus(302);
});

/**
 * @OA\Get(
 *     path="/home",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina home.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina home.phtml")
 * )
 */
$app->get('/home', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'home.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/app",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina servicios.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina servicios.phtml")
 * )
 */
$app->get('/app', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'servicios.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/api/users",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina usuarios.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina usuarios.phtml")
 * )
 */
$app->get('/users', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'usuarios.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/api/activos",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina perfil del usuario.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina activos.phtml")
 * )
 */
$app->get('/profile', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'profile.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/repositorioVulns",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina repositorioVulns.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina repositorioVulns.phtml")
 * )
 */
$app->get('/repositorioVulns', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'repositorioVulns.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/bia",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina bia.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina bia.phtml")
 * )
 */
$app->get('/bia', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'bia.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/terminosdeuso",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina terminos_uso.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina terminos_uso.phtml")
 * )
 */
$app->get('/terminosdeuso', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'terminos_uso.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/politicadeprivacidad",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina politicadeprivacidad.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina politicadeprivacidad.phtml")
 * )
 */
$app->get('/politicadeprivacidad', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'politicadeprivacidad.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/index",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina index.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina index.phtml")
 * )
 */
$app->get('/index', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'index.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/plan",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina plan.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina plan.phtml")
 * )
 */
$app->get('/plan', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'plan.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/reportarkpms",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina kpms.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina kpms.phtml")
 * )
 */
$app->get('/reportarkpms', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'kpms.phtml';
	return $response;
});


/**
 * @OA\Get(
 *     path="/issueDetail",
 *     tags={"Vistas"},
 *     summary="Redirige a la pagina issueDetail.phtml",
 *     @OA\Response(response="200", description="Redirige a la pagina issueDetail.phtml")
 * )
 */
$app->get('/issueDetail', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'issueDetail.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/pac",
 *     tags={"Vistas"},
 *     summary="Redirige a los seguimientos de pac",
 *     @OA\Response(response="200", description="Redirige a los seguimientos de pac")
 * )
 */
$app->get('/pac', function (Request $request, Response $response) {
	return cargarVista('pac', $request, $response);
});

/**
 * @OA\Get(
 *     path="/continuidad",
 *     tags={"Vistas"},
 *     summary="Redirige a la vista de continuidad.",
 *     @OA\Response(response="200", description="Redirige a la vista de continuidad.")
 * )
 */
$app->get('/continuidad', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'continuidad.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/evaluarservicio",
 *     tags={"Vistas"},
 *     summary="Redirige a la vista de los cuestionarios de análisis de riesgo de un activo.",
 *     @OA\Response(response="200", description="Redirige a la vista de los cuestionarios de análisis de riesgo de un activo.")
 * )
 */
$app->get('/evaluarservicio', function (Request $request, Response $response) {
	return cargarVista('eval', $request, $response);
});

/**
 * @OA\Get(
 *     path="/historialservicio",
 *     tags={"Vistas"},
 *     summary="Muestra las gráficas de los análisis de riesgo de un servicio.",
 *     @OA\Response(response="200", description="Muestra las gráficas de los análisis de riesgo de un servicio.")
 * )
 */
$app->get('/historialservicio', function (Request $request, Response $response) {
	return cargarVista('historial', $request, $response);
});

/**
 * @OA\Get(
 *     path="/dashboard",
 *     tags={"Vistas"},
 *     summary="Redirige a la página dashboard.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página dashboard.phtml.")
 * )
 */
$app->get('/dashboard', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'dashboard.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/evalmanager",
 *     tags={"Vistas"},
 *     summary="Redirige a la página del gestor de evaluaciones (evalmanager.phtml)",
 *     @OA\Response(response="200", description="Redirige a la página evalmanager.phtml.")
 * )
 */
$app->get('/evalmanager', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'evalmanager.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/evs",
 *     tags={"Vistas"},
 *     summary="Redirige a la página EVS.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página EVS.phtml.")
 * )
 */
$app->get('/evs', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'EVS.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/pentestRequest",
 *     tags={"Vistas"},
 *     summary="Redirige a la página pentestRequest.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página solicitudes.phtml.")
 * )
 */
$app->get('/pentestRequest', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'pentestRequest.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/solicitudes",
 *     tags={"Vistas"},
 *     summary="Redirige a la página solicitudes.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página EVS.phtml.")
 * )
 */
$app->get('/solicitudes', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'solicitudes.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/normativas",
 *     tags={"Vistas"},
 *     summary="Redirige a la página normativas.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página normativas.phtml.")
 * )
 */
$app->get('/normativas', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'normativas.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/eas",
 *     tags={"Vistas"},
 *     summary="Redirige a la página EAS.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página EAS.phtml.")
 * )
 */
$app->get('/eas', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'EAS.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/logs",
 *     tags={"Vistas"},
 *     summary="Redirige a la página login.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página login.phtml.")
 * )
 */
$app->get('/logs', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'logs.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/login",
 *     tags={"Vistas"},
 *     summary="Redirige a la página login.phtml.",
 *     @OA\Response(response="200", description="Redirige a la página login.phtml.")
 * )
 */
$app->get('/login', function (Request $_, Response $response) {
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'login.phtml';
	return $response;
});

/**
 * @OA\Get(
 *     path="/auth",
 *     tags={"Autenticación"},
 *     summary="Se encarga de comprobar la autenticación local.",
 *     @OA\Parameter(
 *         name="code",
 *         in="query",
 *         required=true,
 *         description="Preguntar a Raúl.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Se encarga de comprobar la autenticación local.")
 * )
 */
$app->get('/auth', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getQueryParams();
	$code = $parametros["code"];
	$tenant = "9744600e-3e04-492e-baa1-25ec245c6f10";
	$client_id = "{client_id_o365}";
	$client_secret = "{client_secret_o365}";
	$callback = "https://11certools.cisocdo.com/auth";
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded'));

	curl_setopt($ch, CURLOPT_URL, "https://login.microsoftonline.com/$tenant/oauth2/v2.0/token");
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt(
		$ch,
		CURLOPT_POSTFIELDS,
		"client_id=$client_id&grant_type=authorization_code&scope=openid profile&code=$code&redirect_uri=$callback&client_secret=$client_secret"
	);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$respuesta =  json_decode(curl_exec($ch), 1);
	curl_close($ch);

	if (isset($respuesta["access_token"])) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $respuesta["access_token"], 'Content-type: application/json'));
		curl_setopt($ch, CURLOPT_URL, "https://graph.microsoft.com/v1.0/me");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data_user = json_decode(curl_exec($ch), 1);
		curl_close($ch);
		if (isset($data_user["mail"])) {
			$db = new Usuarios(DB_USER);
			$user = $db->authUser($data_user["mail"]);
			if (isset($user[0])) {
				$response_data[ERROR] = false;
				$response_data[MESSAGE] = 'Identificado correctamente';

				// Identificación del cliente
				$aud = '';
				$aud .= rawurlencode($_SERVER['HTTP_USER_AGENT']);
				$aud .= gethostname();
				// Creación del token JWT
				$jwt = [
					"iss" => "https://11certools.cisocdo.com",
					"aud" => $aud,
					"iat" => time(),
					"exp" => time() + 3600,
					"jti" => bin2hex(random_bytes(16)),
					'samesite' => 'Strict',
					"data" => $user[0]["id"],
				];
				$token = new TokenEncryptor();
				$token->encrypt(json_encode($jwt), "AES-256-CBC", "");
				// Almacenamiento del token en una cookie y actualización del token en la base de datos
			} else {
				$user = $db->newUser($data_user["mail"]);
				if (!$user[ERROR]) {
					$db->authUser($data_user["mail"]);
				}
			}
		} else {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = "No se ha podido obtener la información del correo electrónico de la sesión de microsoft.";
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha obtenido comunicación con el servidor de microsoft para el login.";
	}
	require_once '..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'sso.phtml';
	return $response;
});

/**
 * @OA\Post(
 *     path="/obtainVulnsDocument",
 *     tags={"Evaluaciones"},
 *     summary="Descarga un documento de vulnerabilidades.",
 *     @OA\Response(response="200", description="Descarga un documento de vulnerabilidades.")
 * )
 */
$app->Post('/api/obtainVulnsDocument', function (Request $request, Response $_) {
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = MENSAJE_ACTIVOS_OBTENIDOS;
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_SERV);

	try {
		$vulnerabilidades = $db->getVulnerabilidades();

		if (isset($parametros['analysisTypes']) && is_array($parametros['analysisTypes'])) {
			$vulnerabilidades = array_filter($vulnerabilidades, function ($vuln) use ($parametros) {
				return in_array($vuln["tipo_prueba"], $parametros['analysisTypes']);
			});
			$vulnerabilidades = array_values($vulnerabilidades);
		}

		if (empty($vulnerabilidades)) {
			throw new RouteException("No se han encontrado vulnerabilidades.");
		}

		$info_vulns = getVulnsUnicas($vulnerabilidades);
		$array_activos = refineVulnsUnicas($info_vulns, $vulnerabilidades);

		$includeDetailedAssetInfo = isset($parametros['detailedInfo']) && $parametros['detailedInfo'] === 'yes';
		$criticidadType = $parametros["criticidadType"] ?? [];
		$estadoType = $parametros["estadoType"] ?? [];

		foreach ($array_activos as &$activo) {
			if ($includeDetailedAssetInfo) {
				$activoInfo = $db->getActivo($activo['id_activo']);
				if (isset($activoInfo[0])) {
					$familia = $db->getFathersNew($activoInfo[0]['id']);
					$familiaOrdenada = ordenarFamilia($familia, $activoInfo[0]['id']);
					$familiaEstructurada = estructurarFamilia($familiaOrdenada);

					$activo['direccion'] = "";
					$activo['area'] = "";
					$activo['unidad'] = "";

					foreach ($familiaEstructurada as $elemento) {
						if (isset($elemento['Direccion'])) {
							$activo['direccion'] = $elemento['Direccion'];
						}
						if (isset($elemento['Area'])) {
							$activo['area'] = $elemento['Area'];
						}
						if (isset($elemento['Unidad'])) {
							$activo['unidad'] = $elemento['Unidad'];
						}
					}
				}
			}

			if (isset($activo['vulns']) && is_array($activo['vulns'])) {
				$activo['vulns'] = array_filter($activo['vulns'], function ($vuln) use ($criticidadType) {
					return isset($vuln["fields"]["customfield_25603"]["value"]) &&
						in_array($vuln["fields"]["customfield_25603"]["value"], $criticidadType);
				});
				$activo['vulns'] = array_filter($activo['vulns'], function ($vuln) use ($estadoType) {
					return isset($vuln["fields"]["status"]["name"]) &&
						in_array($vuln["fields"]["status"]["name"], $estadoType);
				});
				$activo['vulns'] = array_values($activo['vulns']);
			}
		}
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $e->getMessage();
	}

	if ($parametros['format'] == 'Excel') {
		$centerCell = function ($sheet, $range) {
			$sheet->getStyle($range)->getAlignment()
				->setVertical(Alignment::VERTICAL_CENTER)
				->setHorizontal(Alignment::HORIZONTAL_CENTER);
		};

		$setAutoSize = function ($sheet, $columns) {
			foreach ($columns as $column) {
				$sheet->getColumnDimension($column)->setAutoSize(true);
			}
		};

		$mergeCells = function ($sheet, $start, $end, $column) use ($centerCell) {
			if ($start < $end) {
				$mergeRange = "{$column}{$start}:{$column}{$end}";
				$sheet->mergeCells($mergeRange);
				$centerCell($sheet, $mergeRange);
			} elseif ($start == $end) {
				$centerCell($sheet, "{$column}{$start}");
			}
		};

		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()
			->setCreator(NAMETOOL)
			->setTitle('Datos repositorio de vulnerabilidades de 11CertTool')
			->setSubject('Documento de vulnerabilidades')
			->setDescription('Es un documento de vulnerabilidades generado por 11CertTool')
			->setKeywords('11CertTool');

		$sheet = $spreadsheet->setActiveSheetIndex(0);

		if ($includeDetailedAssetInfo) {
			$headers = ['Dirección', 'Área', 'Unidad', 'Producto', 'Analysis Type', 'Issue Key', 'Estado', 'Criticidad', 'Issue Producto', 'Resumen', 'Fecha Creación'];
			$headerRange = "A1:K1";
			$columns = range('A', 'K');
		} else {
			$headers = ['Producto', 'Analysis Type', 'Issue Key', 'Estado', 'Criticidad', 'Issue Producto', 'Resumen', 'Fecha Creación'];
			$headerRange = "A1:H1";
			$columns = range('A', 'H');
		}
		foreach ($headers as $index => $header) {
			$sheet->setCellValue(chr(65 + $index) . '1', $header);
		}

		$centerCell($sheet, $headerRange);
		$sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(14);
		$setAutoSize($sheet, $columns);

		$vulnContador = 2;
		$i = 2;
		$mergeControls = ['current' => ['', '', '', ''], 'start' => [2, 2, 2, 2]];

		foreach ($array_activos as $activoRecorrido) {
			if (empty($activoRecorrido["vulns"])) {
				continue;
			}
			if ($includeDetailedAssetInfo) {
				$newValues = [
					$activoRecorrido["direccion"] ?? "",
					$activoRecorrido["area"] ?? "",
					$activoRecorrido["unidad"] ?? "",
					$activoRecorrido["nombre"]
				];

				$columns_merge = ['A', 'B', 'C', 'D'];

				for ($j = 0; $j < 4; $j++) {
					$hasChanged = $mergeControls['current'][$j] !== "" &&
						($mergeControls['current'][$j] !== $newValues[$j] ||
							($j > 0 && $mergeControls['current'][$j - 1] !== $newValues[$j - 1]));

					if ($hasChanged) {
						$mergeCells($sheet, $mergeControls['start'][$j], $vulnContador - 1, $columns_merge[$j]);
						$mergeControls['start'][$j] = $vulnContador;
					}
				}

				$mergeControls['current'] = $newValues;

				for ($j = 0; $j < 4; $j++) {
					$sheet->setCellValue($columns_merge[$j] . $vulnContador, $newValues[$j]);
					$centerCell($sheet, $columns_merge[$j] . $vulnContador);
				}
			}

			foreach ($activoRecorrido["vulns"] as $vuln) {
				if ($vuln != null) {
					$key = $vuln["key"] ?? "Issue eliminada desde fuera de 11Cert";
					$copyKey = $vuln["fields"]["issuelinks"][0]["inwardIssue"]["key"] ?? "Clonada no disponible";

					$date = isset($vuln["fields"]["created"]) ? (new DateTime($vuln["fields"]["created"]))->format('d/m/Y') : "Sin fecha";

					if ($includeDetailedAssetInfo) {
						$vulnData = [
							'E' => $vuln["pruebaInfo"]["tipo_prueba"],
							'F' => $key,
							'G' => $vuln["fields"]["status"]["name"],
							'H' => $vuln["fields"]["customfield_25603"]["value"],
							'I' => $copyKey,
							'J' => $vuln["fields"]["summary"],
							'K' => $date
						];
					} else {
						$sheet->setCellValue('A' . $i, $activoRecorrido["nombre"]);
						$vulnData = [
							'B' => $vuln["pruebaInfo"]["tipo_prueba"],
							'C' => $key,
							'D' => $vuln["fields"]["status"]["name"],
							'E' => $vuln["fields"]["customfield_25603"]["value"],
							'F' => $copyKey,
							'G' => $vuln["fields"]["summary"],
							'H' => $date
						];
					}

					foreach ($vulnData as $col => $value) {
						$sheet->setCellValue($col . $vulnContador, $value);
					}
					$vulnContador++;
				}
			}

			if (!$includeDetailedAssetInfo && $i != $vulnContador) {
				$mergeCells($sheet, $i, $vulnContador - 1, 'A');
				$centerCell($sheet, 'A' . $i);
				$i = $vulnContador;
			}
		}

		if ($includeDetailedAssetInfo) {
			$columns_merge = ['A', 'B', 'C', 'D'];
			for ($j = 0; $j < 4; $j++) {
				$mergeCells($sheet, $mergeControls['start'][$j], $vulnContador - 1, $columns_merge[$j]);
			}
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header("Content-Disposition: attachment;filename=vulnerabilidades.xlsx");
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}
});

/**
 * @OA\Get(
 *     path="/downloadEval",
 *     tags={"Evaluaciones"},
 *     summary="Descarga una Evaluación ya realizada pasándole una fecha como parámetro.",
 * 	   @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación que queremos descargar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Descarga una Evaluación ya realizada pasándole una fecha como parámetro.")
 * )
 */
$app->get('/downloadEval', function (Request $request, Response $_) {
	$resultado = array();
	$parametros = $request->getQueryParams();
	$db = new DbOperations(DB_SERV);
	$fecha = $parametros["fecha"];
	$eval = $db->getPreguntasEvaluacionByFecha($parametros["fecha"]);
	if (isset($eval[0])) {
		$eval = json_decode($eval[0]['preguntas'], true);
		if (!isset($eval['3ps'])) {
			foreach ($eval as $key => $value) {
				$duda = $db->getPreguntasById($key);
				if ($value == "0") {
					$value = "No";
				} else {
					$value = "Si";
				}
				array_push($resultado, array($duda[0]['id'], $duda[0]['duda'], $value));
			}
		} else {
			$resultado = array("Es una evaluación 3ps y no se puede exportar.");
		}
	}
	$spreadsheet = new Spreadsheet();
	$spreadsheet->getProperties()->setCreator(NAMETOOL)
		->setTitle('Exportación de evaluación')
		->setSubject('Es una exportación 11CertTool')
		->setDescription('Es una exportación de una evaluación ya realizada')
		->setKeywords('11Cert Tool');
	$spreadsheet->setActiveSheetIndex(0)
		->setCellValue('A1', "ID")
		->setCellValue('B1', "DUDA")
		->setCellValue('C1', "RESPUESTA")
		->fromArray($resultado, null, 'A2');
	$spreadsheet->getActiveSheet()->setTitle('Eval');
	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header("Content-Disposition: attachment;filename=Export_eval_$fecha.xlsx");
	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
	exit;
});

/**
 * @OA\Get(
 *     path="/api/getDashboardCriticidadProductos",
 *     tags={"Evaluación / EAE"},
 *     summary="Obtiene la criticidad máxima de los BIAs por producto.",
 *     @OA\Response(response="200", description="Devuelve la criticidad máxima de los BIAs por producto.")
 * )
 */
$app->get('/api/getDashboardCriticidadProductos', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);

	$productos = $db->getActivosByTipo(67, null);

	$criticidadProductos = [];
	$leves = 0;
	$bajos = 0;
	$moderados = 0;
	$altos = 0;
	$criticos = 0;
	$no_evaluados = 0;
	$criticidadGrafico = [
		["Criticidad", "Cantidad"],
		["Leve", $leves],
		["Bajo", $bajos],
		["Moderado", $moderados],
		["Alto", $altos],
		["Crítico", $criticos],
		["No evaluado", $no_evaluados],
	];

	foreach ($productos as $producto) {
		$servicios = $db->getHijosTipo($producto["id"], "Servicio de Negocio");
		$servicios_activos = array_filter($servicios, function($servicio) {
			return !(isset($servicio["archivado"]) && $servicio["archivado"] == 1);
		});
		if (count($servicios_activos) == 0) {
			continue;
		}
		$maxCriticidad = -1;
		$maxConf = -1;
		$maxInt = -1;
		$maxDisp = -1;
		$fechaBia = "";
		$serviciosNombres = [];

		foreach ($servicios_activos as $servicio) {
			$bia = $db->getBia($servicio["id"]);
			if (isset($bia[0]["meta_value"])) {
				$biaCalc = calcularBia($bia);
				$critArray = [
					$biaCalc["Con"]["Max"] ?? 0,
					$biaCalc["Int"]["Max"] ?? 0,
					$biaCalc["Dis"]["Max"] ?? 0
				];
				$serviciosNombres[] = $servicio["nombre"];
				$maxCriticidad = max($maxCriticidad, max($critArray));
				$maxConf = max($maxConf, $biaCalc["Con"]["Max"] ?? 0);
				$maxInt = max($maxInt, $biaCalc["Int"]["Max"] ?? 0);
				$maxDisp = max($maxDisp, $biaCalc["Dis"]["Max"] ?? 0);
				$fechaBia = $bia[0]["fecha"] ?? $fechaBia;
			}
		}

		$arrayCriticidad = ["Leve", "Bajo", "Moderado", "Alto", "Crítico"];
		$criticidadTxt = $maxCriticidad >= 0 ? $arrayCriticidad[$maxCriticidad] : "No evaluado";
		$confTxt = $maxConf >= 0 ? $arrayCriticidad[$maxConf] : "No evaluado";
		$intTxt = $maxInt >= 0 ? $arrayCriticidad[$maxInt] : "No evaluado";
		$dispTxt = $maxDisp >= 0 ? $arrayCriticidad[$maxDisp] : "No evaluado";

		foreach ($criticidadGrafico as &$row) {
			if ($row[0] == $criticidadTxt) {
				$row[1]++;
				break;
			}
		}


		$criticidadProductos[] = [
			"id" => $producto["id"],
			"producto" => $producto["nombre"],
			"criticidad" => $criticidadTxt,
			"confidencialidad" => $confTxt,
			"integridad" => $intTxt,
			"disponibilidad" => $dispTxt,
			"servicios" => implode(", ", $serviciosNombres),
			"fecha_bia" => $fechaBia
		];
	}

	$response_data = [
		"error" => false,
		"criticidadProductos" => $criticidadProductos,
		"criticidadGrafico" => $criticidadGrafico
	];
	$response->getBody()->write(json_encode($response_data));
	return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/downloadAsuncionSeguimiento",
 *     tags={"Evaluación / PAC"},
 *     summary="Genera un documento Word de asunción de riesgo en el seguimiento de PAC.Redirige a los seguimientos de pac.",
 * 	   @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID Numérico del seguimiento de PAC.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera un documento Word de asunción de riesgo en el seguimiento de PAC.Redirige a los seguimientos de pac.")
 * )
 */
$app->get('/downloadAsuncionSeguimiento', function (Request $request, Response $_) {
	global $error;
	$parametros = $request->getQueryParams();
	$id = $parametros["id"];
	if (isset($id) && !empty($id) && is_numeric($id)) {
		$db = new Activos(DB_SERV);
		$seguimientopac = $db->getSeguimientoById($id);
		if (isset($seguimientopac[0])) {
			$nombrepac =  $seguimientopac[0]["nombrepac"];
			$activoid = $seguimientopac[0]["activo_id"];
			$sistema = $db->getActivo($activoid);
			$fecha = $seguimientopac[0]["evaluacion_id"];
		}
		$direccion = getParentescobySistemaId($sistema, 124);
		$servicio = getServiciobySistemaId($sistema);
		$nombreservicio = $servicio[0]["padres"][0]["nombre"];
		$activos = getActivosParaEvaluacion($sistema[0]["id"]);
		$preguntas = $db->getPreguntasEvaluacionByFecha($fecha);
		if (isset($preguntas[0][PREGUNTAS])) {
			$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
			$preguntas = prepararPreguntas($preguntas, $parametros["id"]);
		}
		$bia = $db->getBia($servicio[0]["padres"][0]["id"]);
		if (isset($bia)) {
			$resultadobia = calcularBia($bia);
		}
		if ($fecha !== 'null') {
			$eval = getEvaluacionActivos($activos, $preguntas);
			getcalculoamenazas($eval["amenazas"], $resultadobia);
		}
	}
	$listaPac["listpac"] = array(["listpac" => $nombrepac]);
	$templateProcessor = new TemplateProcessor('../plantilla/CDCO-11cert_asuncion.docx');

	// DATOS DEL DOCUMENTO FECHA,SERVICIO, SISTEMA,ETC
	$templateProcessor->setValue('{fecha}', date('d/m/Y', time()));
	$templateProcessor->setValue('{servicio}', $servicio[0]["padres"][0]["nombre"]);
	$templateProcessor->setValue('{sistema}', $sistema[0]["nombre"]);
	$templateProcessor->setValue('{direccion}', $direccion["nombre"]);
	$templateProcessor->cloneBlock('block_listpac', count($listaPac["listpac"]), true, false, $listaPac["listpac"]);

	$templateProcessor->setValue('{textolibre}', "[Comentarios o texto libre]");
	$templateProcessor->setValue('{tablariesgos}', "TABLA DE RIESGOS");
	$templateProcessor->setUpdateFields();
	$temp_file = tempnam(sys_get_temp_dir(), 'PHPWord');
	$templateProcessor->saveAs($temp_file);
	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header("Content-Disposition: attachment; filename=CISOCDCO-11cert-AceptaciónRiesgo-$nombreservicio-$nombrepac.docx");
	readfile($temp_file);
	unlink($temp_file);
	exit;
});

/**
 * @OA\Get(
 *     path="/downloadErs",
 *     tags={"Evaluación / ERS"},
 *     summary="Genera un documento Word con la información de ERS de un activo en una fecha concreta.",
 * 	   @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="activo",
 *         in="query",
 *         required=true,
 *         description="ID del activo que queramos descargar el ERS.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera un documento Word con la información de ERS de un activo en una fecha concreta.")
 * )
 */
$app->get('/downloadErs', function (Request $request, Response $_) {
	global $error;
	$parametros = $request->getQueryParams();
	$fecha = $parametros["fecha"];
	$id = $parametros["activo"];
	if (isset($id) && !empty($id) && is_numeric($id) && isset($fecha)) {
		$version = null;
		if (isset($parametros["version"])) {
			$version = $parametros["version"];
		}
		$db = new Activos(DB_SERV);
		$sistema = $db->getActivo($id);
		$direccion = getParentescobySistemaId($sistema, 124);
		$area = getParentescobySistemaId($sistema, 123);
		if (isset($area["nombre"])) {
			$direccionarea = $direccion["nombre"] . " / " . $area["nombre"];
		} else {
			$direccionarea = $direccion["nombre"];
		}
		$servicio = getServiciobySistemaId($sistema);
		$bia = $db->getBia($servicio[0]["padres"][0]["id"]);
		if (isset($bia)) {
			$resultadobia = calcularBia($bia);
			$niveles = array(0 => 'Leve', 1 => 'Medio', 2 => 'Moderado', 3 => 'Alto', 4 => 'Critico');
		}
		$activos = getActivosParaEvaluacion($id);
		if ($fecha !== 'null') {
			$preguntas = $db->getPreguntasEvaluacionByFecha($fecha);
			if ($version !== "undefined") {
				$preguntas = $db->getPreguntasversionByFecha($version);
			}
			if (isset($preguntas[0][PREGUNTAS])) {
				$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
				$preguntas = prepararPreguntas($preguntas, $id);
			}
			$eval = getEvaluacionActivos($activos, $preguntas);
			$amenazas = getcalculoamenazas($eval["amenazas"], $resultadobia);
		}
	}
	$templateProcessor = new TemplateProcessor('../plantilla/CDCO-11cert_ers.docx');

	// DATOS DEL DOCUMENTO FECHA,SERVICIO, SISTEMA,ETC
	$templateProcessor->setValue('{fecha}', date('d/m/Y', time()));
	$templateProcessor->setValue('{servicio}', $servicio[0]["padres"][0]["nombre"]);
	$templateProcessor->setValue('{sistema}', $sistema[0]["nombre"]);
	$templateProcessor->setValue('{direccionarea}', $direccionarea);

	//RESUMEN DE AMENAZAS TABLA TOTAL POR ACTIVO
	//CAMBIAR POR RIESGOS UNICOS
	$templateProcessor->setValue('{amenazatotal}', count($eval["amenazas"]));
	$templateProcessor->setValue('{muybajo}', $amenazas["resumen"]["Leve"]);
	$templateProcessor->setValue('{bajo}', $amenazas["resumen"]["Medio"]);
	$templateProcessor->setValue('{medio}', $amenazas["resumen"]["Moderado"]);
	$templateProcessor->setValue('{alto}', $amenazas["resumen"]["Alto"]);
	$templateProcessor->setValue('{muyalto}', $amenazas["resumen"]["Crítico"]);

	$templateProcessor->setValue('{1value}', $niveles[0]);
	$templateProcessor->setValue('{2value}', $niveles[1]);
	$templateProcessor->setValue('{3value}', $niveles[2]);
	$templateProcessor->setValue('{4value}', $niveles[3]);
	$templateProcessor->setValue('{5value}', $niveles[4]);

	// RIESGO ACTUAL MAPA CALOR
	$templateProcessor->setValue('{riesgoa}', $amenazas["riesgoa"]);

	// NIVELES DIMENSIONES DEL BIA
	$templateProcessor->setValue('{conf}', $niveles[$resultadobia["Con"]["Max"]]);
	$templateProcessor->setValue('{int}', $niveles[$resultadobia["Int"]["Max"]]);
	$templateProcessor->setValue('{disp}', $niveles[$resultadobia["Dis"]["Max"]]);

	// MAPA DE CALOR RIESGO ACTUAL
	$templateProcessor->setValue('{a0,0}', $amenazas["actual"][0][0]);
	$templateProcessor->setValue('{a1,0}', $amenazas["actual"][1][0]);
	$templateProcessor->setValue('{a2,0}', $amenazas["actual"][2][0]);
	$templateProcessor->setValue('{a3,0}', $amenazas["actual"][3][0]);
	$templateProcessor->setValue('{a4,0}', $amenazas["actual"][4][0]);

	$templateProcessor->setValue('{a0,1}', $amenazas["actual"][0][1]);
	$templateProcessor->setValue('{a1,1}', $amenazas["actual"][1][1]);
	$templateProcessor->setValue('{a2,1}', $amenazas["actual"][2][1]);
	$templateProcessor->setValue('{a3,1}', $amenazas["actual"][3][1]);
	$templateProcessor->setValue('{a4,1}', $amenazas["actual"][4][1]);

	$templateProcessor->setValue('{a0,2}', $amenazas["actual"][0][2]);
	$templateProcessor->setValue('{a1,2}', $amenazas["actual"][1][2]);
	$templateProcessor->setValue('{a2,2}', $amenazas["actual"][2][2]);
	$templateProcessor->setValue('{a3,2}', $amenazas["actual"][3][2]);
	$templateProcessor->setValue('{a4,2}', $amenazas["actual"][4][2]);

	$templateProcessor->setValue('{a0,3}', $amenazas["actual"][0][3]);
	$templateProcessor->setValue('{a1,3}', $amenazas["actual"][1][3]);
	$templateProcessor->setValue('{a2,3}', $amenazas["actual"][2][3]);
	$templateProcessor->setValue('{a3,3}', $amenazas["actual"][3][3]);
	$templateProcessor->setValue('{a4,3}', $amenazas["actual"][4][3]);

	$templateProcessor->setValue('{a0,4}', $amenazas["actual"][0][4]);
	$templateProcessor->setValue('{a1,4}', $amenazas["actual"][1][4]);
	$templateProcessor->setValue('{a2,4}', $amenazas["actual"][2][4]);
	$templateProcessor->setValue('{a3,4}', $amenazas["actual"][3][4]);
	$templateProcessor->setValue('{a4,4}', $amenazas["actual"][4][4]);

	// MAPA DE CALOR RIESGO INHERENTE
	$templateProcessor->setValue('{i0,0}', $amenazas["inherente"][0][0]);
	$templateProcessor->setValue('{i1,0}', $amenazas["inherente"][1][0]);
	$templateProcessor->setValue('{i2,0}', $amenazas["inherente"][2][0]);
	$templateProcessor->setValue('{i3,0}', $amenazas["inherente"][3][0]);
	$templateProcessor->setValue('{i4,0}', $amenazas["inherente"][4][0]);

	$templateProcessor->setValue('{i0,1}', $amenazas["inherente"][0][1]);
	$templateProcessor->setValue('{i1,1}', $amenazas["inherente"][1][1]);
	$templateProcessor->setValue('{i2,1}', $amenazas["inherente"][2][1]);
	$templateProcessor->setValue('{i3,1}', $amenazas["inherente"][3][1]);
	$templateProcessor->setValue('{i4,1}', $amenazas["inherente"][4][1]);

	$templateProcessor->setValue('{i0,2}', $amenazas["inherente"][0][2]);
	$templateProcessor->setValue('{i1,2}', $amenazas["inherente"][1][2]);
	$templateProcessor->setValue('{i2,2}', $amenazas["inherente"][2][2]);
	$templateProcessor->setValue('{i3,2}', $amenazas["inherente"][3][2]);
	$templateProcessor->setValue('{i4,2}', $amenazas["inherente"][4][2]);

	$templateProcessor->setValue('{i0,3}', $amenazas["inherente"][0][3]);
	$templateProcessor->setValue('{i1,3}', $amenazas["inherente"][1][3]);
	$templateProcessor->setValue('{i2,3}', $amenazas["inherente"][2][3]);
	$templateProcessor->setValue('{i3,3}', $amenazas["inherente"][3][3]);
	$templateProcessor->setValue('{i4,3}', $amenazas["inherente"][4][3]);

	$templateProcessor->setValue('{i0,4}', $amenazas["inherente"][0][4]);
	$templateProcessor->setValue('{i1,4}', $amenazas["inherente"][1][4]);
	$templateProcessor->setValue('{i2,4}', $amenazas["inherente"][2][4]);
	$templateProcessor->setValue('{i3,4}', $amenazas["inherente"][3][4]);
	$templateProcessor->setValue('{i4,4}', $amenazas["inherente"][4][4]);

	// MAPA DE CALOR RIESGO RESIDUAL
	$templateProcessor->setValue('{r0,0}', $amenazas["residual"][0][0]);
	$templateProcessor->setValue('{r1,0}', $amenazas["residual"][1][0]);
	$templateProcessor->setValue('{r2,0}', $amenazas["residual"][2][0]);
	$templateProcessor->setValue('{r3,0}', $amenazas["residual"][3][0]);
	$templateProcessor->setValue('{r4,0}', $amenazas["residual"][4][0]);

	$templateProcessor->setValue('{r0,1}', $amenazas["residual"][0][1]);
	$templateProcessor->setValue('{r1,1}', $amenazas["residual"][1][1]);
	$templateProcessor->setValue('{r2,1}', $amenazas["residual"][2][1]);
	$templateProcessor->setValue('{r3,1}', $amenazas["residual"][3][1]);
	$templateProcessor->setValue('{r4,1}', $amenazas["residual"][4][1]);

	$templateProcessor->setValue('{r0,2}', $amenazas["residual"][0][2]);
	$templateProcessor->setValue('{r1,2}', $amenazas["residual"][1][2]);
	$templateProcessor->setValue('{r2,2}', $amenazas["residual"][2][2]);
	$templateProcessor->setValue('{r3,2}', $amenazas["residual"][3][2]);
	$templateProcessor->setValue('{r4,2}', $amenazas["residual"][4][2]);

	$templateProcessor->setValue('{r0,3}', $amenazas["residual"][0][3]);
	$templateProcessor->setValue('{r1,3}', $amenazas["residual"][1][3]);
	$templateProcessor->setValue('{r2,3}', $amenazas["residual"][2][3]);
	$templateProcessor->setValue('{r3,3}', $amenazas["residual"][3][3]);
	$templateProcessor->setValue('{r4,3}', $amenazas["residual"][4][3]);

	$templateProcessor->setValue('{r0,4}', $amenazas["residual"][0][4]);
	$templateProcessor->setValue('{r1,4}', $amenazas["residual"][1][4]);
	$templateProcessor->setValue('{r2,4}', $amenazas["residual"][2][4]);
	$templateProcessor->setValue('{r3,4}', $amenazas["residual"][3][4]);
	$templateProcessor->setValue('{r4,4}', $amenazas["residual"][4][4]);

	$temp_file = tempnam(sys_get_temp_dir(), 'PHPWord');
	$templateProcessor->saveAs($temp_file);
	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header("Content-Disposition: attachment; filename=CISOCDO-11cert_" . $servicio[0]["padres"][0]["nombre"] . "-" . $sistema[0]["nombre"] . "_L2[06-ERS]_v1.0.docx");
	readfile($temp_file);
	unlink($temp_file);
	exit;
});

/**
 * @OA\Get(
 *     path="/downloadEcr",
 *     tags={"Evaluación / ECR"},
 *     summary="Genera un documento Word con la información de ECR de un activo en una fecha concreta.",
 *     @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="activo",
 *         in="query",
 *         required=true,
 *         description="ID del activo que queramos descargar el ECR.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera un documento Word con la información de ECR de un activo en una fecha concreta.")
 * )
 */
$app->get('/downloadEcr', function (Request $request, Response $_) {
	$parametros = $request->getQueryParams();
	$fecha = $parametros["fecha"];
	$version = $parametros["version"];
	$id = $parametros["activo"];
	if (isset($id) && !empty($id) && is_numeric($id) && isset($fecha)) {
		$db = new Activos(DB_SERV);
		$sistema = $db->getActivo($id);
		$activos = getActivosParaEvaluacion($id);
		if (isset($version) && $version !== "undefined") {
			$preguntas = $db->getPreguntasversionByFecha($version);
		} else {
			$preguntas = $db->getPreguntasEvaluacionByFecha($fecha);
		}
		if (isset($preguntas[0][PREGUNTAS])) {
			$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
			$preguntas = prepararPreguntas($preguntas, $id);
			if (!isset($preguntas['3ps'])) {
				$comparison = $db->getCompararNormativa("27002(2022),iso27001,iso27701,pbs", $activos, $preguntas);
				$numcontroles = count($comparison["27002(2022)"]);
			} else {
				$comparison = $db->getCompararNormativa("3ps", $activos, $preguntas);
				$numcontroles = count($comparison["3ps"]);
			}
		} else {
			$comparison = "";
		}
		$management = getParentescobySistemaId($sistema, 124);
		$area = getParentescobySistemaId($sistema, 123);
		if (isset($area["nombre"])) {
			$direccionarea = $management["nombre"] . " / " . $area["nombre"];
		} else {
			$direccionarea = $management["nombre"];
		}
		$servicio = getServiciobySistemaId($sistema);
	}
	if (isset($servicio[0]["padres"][0])) {
		$activos = $db->getTree($servicio[0]["padres"][0]);
	}
	if (isset($activos[0])) {
		$sistemas = getActivoByTipo($activos, 33);
		$subsistemas = getActivoByTipo($activos, 30);
	} else {
		$sistemas = [];
		$subsistemas = [];
	}

	$templateProcessor = new TemplateProcessor('../plantilla/CDCO-11cert_ecr_style.docx');
	// DATOS DEL DOCUMENTO FECHA,SERVICIO, SISTEMA,ETC
	$templateProcessor->setValue('{fecha}', date('d/m/Y', time()));
	$templateProcessor->setValue('{servicio}', $servicio[0]["padres"][0]["nombre"]);
	$templateProcessor->setValue('{sistema}', $sistema[0]["nombre"]);
	$templateProcessor->setValue('{direccionarea}', $direccionarea);
	$templateProcessor->setValue('{numcontroles}', $numcontroles);
	$templateProcessor->setValue('{numsistemas}', count($sistemas));
	$templateProcessor->setValue('{numsubsistemas}', count($subsistemas));
	if (isset($subsistemas[0])) {
		$templateProcessor->setValue('{subsistemas}', implode("<w:br/>", array_column($subsistemas, "nombre")));
	} else {
		$templateProcessor->setValue('{subsistemas}', "");
	}

	$templateProcessor->setValue('{descripcionsistema}', "");
	foreach ($comparison as $norma => $values) {
		$series[$norma] = contarCumplimiento($values);
	}
	$categories = ['CT', 'CP', 'NC', 'NE'];

	$stylepie = [
		'width' => 2500000,
		'height' => 2500000,
		'showAxisLabels' => false,
		'showGridX' => false,
		'showGridY' => false,
		'showLegend' => true,
		'legendPosition' => 't',
		'dataLabelOptions' => array(
			'showCatName' => false,
			'showVal' => false,
			'showPercent' => true
		)
	];

	$stylebar = [
		'width' => 2500000,
		'height' => 2500000,
		'showAxisLabels' => true,
		'showGridX' => false,
		'showGridY' => true,
		'showLegend' => false,
		'dataLabelOptions' => array(
			'showCatName' => false,
			'showVal' => false
		)
	];

	$piechartisonew = new Chart('doughnut', $categories, $series["27002(2022)"]["total"], $stylepie);
	$piechartprivacidad = new Chart('doughnut',  $categories, $series["iso27701"]["total"], $stylepie);
	$piechartpbs = new Chart('doughnut',  $categories, $series["pbs"]["total"], $stylepie);

	$barchartisonew = new Chart('percent_stacked_bar', $series["27002(2022)"]["dominios"], array_column($series["27002(2022)"]["cumplimiento"], "ct"), $stylebar, "CT");
	$barchartisonew->addSeries($series["27002(2022)"]["dominios"], array_column($series["27002(2022)"]["cumplimiento"], "cp"), "CP");
	$barchartisonew->addSeries($series["27002(2022)"]["dominios"], array_column($series["27002(2022)"]["cumplimiento"], "nc"), "NC");
	$barchartisonew->addSeries($series["27002(2022)"]["dominios"], array_column($series["27002(2022)"]["cumplimiento"], "ne"), "NE");


	$barchartprivacidad = new Chart('percent_stacked_bar', $series["iso27701"]["dominios"], array_column($series["iso27701"]["cumplimiento"], "ct"), $stylebar, "CT");
	$barchartprivacidad->addSeries($series["iso27701"]["dominios"], array_column($series["iso27701"]["cumplimiento"], "cp"), "CP");
	$barchartprivacidad->addSeries($series["iso27701"]["dominios"], array_column($series["iso27701"]["cumplimiento"], "nc"), "NC");
	$barchartprivacidad->addSeries($series["iso27701"]["dominios"], array_column($series["iso27701"]["cumplimiento"], "ne"), "NE");

	$barchartpbs = new Chart('percent_stacked_bar', $series["pbs"]["dominios"], array_column($series["pbs"]["cumplimiento"], "ct"), $stylebar, "CT");
	$barchartpbs->addSeries($series["pbs"]["dominios"], array_column($series["pbs"]["cumplimiento"], "cp"), "CP");
	$barchartpbs->addSeries($series["pbs"]["dominios"], array_column($series["pbs"]["cumplimiento"], "nc"), "NC");
	$barchartpbs->addSeries($series["pbs"]["dominios"], array_column($series["pbs"]["cumplimiento"], "ne"), "NE");



	$templateProcessor->setChart('char27001pie', $piechartisonew);
	$templateProcessor->setChart('char27701pie', $piechartprivacidad);
	$templateProcessor->setChart('charpbspie', $piechartpbs);
	$templateProcessor->setChart('char27001barra', $barchartisonew);
	$templateProcessor->setChart('char27701barra', $barchartprivacidad);
	$templateProcessor->setChart('charpbsbarra', $barchartpbs);

	$templateProcessor->setUpdateFields();
	$temp_file = tempnam(sys_get_temp_dir(), 'PHPWord');
	$templateProcessor->saveAs($temp_file);
	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header("Content-Disposition: attachment; filename=CISOCDO-11cert_" . $servicio[0]["padres"][0]["nombre"] . "-" . $sistema[0]["nombre"] . "_L2[05-ECR]_v1.0.docx");
	readfile($temp_file);
	unlink($temp_file);
	exit;
});

/**
 * @OA\Get(
 *     path="/downloadPac",
 *     tags={"Evaluación / PAC"},
 *     summary="Genera un documento Word con la información de PAC de un activo en una fecha concreta.",
 *     @OA\Response(response="200", description="Genera un documento Word con la información de PAC de un activo en una fecha concreta."),
 *     @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha concreta de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="activo",
 *         in="query",
 *         required=true,
 *         description="ID del activo que queramos descargar el PAC.",
 *         @OA\Schema(type="string")
 *     ),
 * )
 */
$app->get('/downloadPac', function (Request $request, Response $_) {
	global $error;
	$parametros = $request->getQueryParams();
	if (isset($parametros["activo"]) && !empty($parametros["activo"]) && is_numeric($parametros["activo"]) && isset($parametros["fecha"])) {
		$id = $parametros["activo"];
		$fecha = $parametros["fecha"];
		$version = null;
		if (isset($parametros["version"])) {
			$version = $parametros["version"];
		}
		$db = new Activos(DB_SERV);
		$sistema = $db->getActivo($id);
		$direccion = getParentescobySistemaId($sistema, 124);
		$area = getParentescobySistemaId($sistema, 123);
		if (isset($area["nombre"])) {
			$direccionarea = $direccion["nombre"] . " / " . $area["nombre"];
		} else {
			$direccionarea = $direccion["nombre"];
		}
		$servicio = getServiciobySistemaId($sistema);
		$activos = getActivosParaEvaluacion($id);
		$seguimientopac = $db->getSeguimientoByActivoId($id);
		$preguntas = $db->getPreguntasEvaluacionByFecha($fecha);
		if ($version !== null) {
			$preguntas = $db->getPreguntasversionByFecha($version);
		}
		if (isset($preguntas[0][PREGUNTAS])) {
			$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
			$preguntas = prepararPreguntas($preguntas, $id);
		}
		$eval = getEvaluacionActivos($activos, $preguntas);
	}

	$pacreturn = array();
	$pacreturnrow = array();
	if (isset($eval["pac"])) {
		$proyectonum = array("total" => 0, "finalizado" => 0, "noiniciado" => 0, "enprogreso" => 0, "descartado" => 0);
		foreach ($eval["pac"] as $cod => $pac) {
			$ct = array_column($pac["usf"], "ctm");
			$index = array_search($cod, array_column($seguimientopac, "codpac"));
			if ($index !== false) {
				$fechaInicio = $seguimientopac[$index]['inicio'];
				$fechaFin = $seguimientopac[$index]['fin'];
				$responsable = $seguimientopac[$index]['responsable'];
				$estado = $seguimientopac[$index]['estado'];
				$comentario = $seguimientopac[$index]['comentarios'];

				if ($estado == "Finalizado") {
					$proyectonum["finalizado"]++;
				} elseif ($estado == "No iniciado") {
					$proyectonum["noiniciado"]++;
				} elseif ($estado == "En Progreso") {
					$proyectonum["enprogreso"]++;
				} elseif ($estado == "Descartado") {
					$proyectonum["descartado"]++;
				} elseif ($estado == "Iniciado") {
					$proyectonum["enprogreso"]++;
				}
			} else {
				$fechaInicio = "";
				$fechaFin = "";
				$responsable = "";
				$estado = "";
				$comentario = "";
			}
			$prioridad = array_sum($ct) / count($ct);
			if ($prioridad > 90) {
				$mediastring = 'Muy Baja';
			} elseif ($prioridad > 80) {
				$mediastring = 'Baja';
			} elseif ($prioridad > 50) {
				$mediastring = 'Media';
			} elseif ($prioridad > 25) {
				$mediastring = 'Alta';
			} else {
				$mediastring = 'Muy Alta';
			}
			if ($prioridad < 80) {
				$prioridad = $mediastring;
				$proyectonum["total"]++;
				if ($index === false) {
					$proyectonum["noiniciado"]++;
				}
				array_push($pacreturnrow, array('codpacrow' => $cod, 'nombrepacrow' => $pac["nombre"], 'prioridadrow' => $prioridad));
				array_push($pacreturn, array('codpac' => $cod, 'nombrepac' => $pac["nombre"], 'descripcionpac' => $pac["descripcion"], 'prioridadpac' => $prioridad, "recomendacionespac" => $pac["tareas"], "iniciopac" => $fechaInicio, "finpac" => $fechaFin, "estado" => $estado, "comentarios" => $comentario, "responsable" => $responsable));
			}
		}
	}

	$templateProcessor = new TemplateProcessor('../plantilla/CDCO-11cert_pac.docx');
	$templateProcessor->cloneRowAndSetValues('codpacrow', $pacreturnrow);
	$templateProcessor->cloneBlock('block_pac', 0, true, false, $pacreturn);
	// DATOS DEL DOCUMENTO FECHA,SERVICIO, SISTEMA,ETC
	$templateProcessor->setValue('{fecha}', date('d/m/Y', time()));
	$templateProcessor->setValue('servicio', $servicio[0]["padres"][0]["nombre"]);
	$templateProcessor->setValue('sistema', $sistema[0]["nombre"]);
	$templateProcessor->setValue('totalpac', $proyectonum["total"]);
	$templateProcessor->setValue('completadopac', $proyectonum["finalizado"]);
	$templateProcessor->setValue('noiniciado', $proyectonum["noiniciado"]);
	$templateProcessor->setValue('enprogreso', $proyectonum["enprogreso"]);
	$templateProcessor->setValue('finalizado', $proyectonum["finalizado"]);
	$templateProcessor->setValue('descartado', $proyectonum["descartado"]);
	$templateProcessor->setValue('direccionarea', $direccionarea);
	$temp_file = tempnam(sys_get_temp_dir(), 'PHPWord');
	$templateProcessor->saveAs($temp_file);
	header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
	header("Content-Disposition: attachment; filename=CISOCDO-11cert_" . $servicio[0]["padres"][0]["nombre"] . "-" . $sistema[0]["nombre"] . "_L2[07-PAC]_v1.0.docx");
	readfile($temp_file);
	unlink($temp_file);
	exit;
});

/**
 * @OA\Get(
 *     path="/downloadActivosTree",
 *     tags={"Activos"},
 *     summary="Devuelve el árbol entero de Dirección, Área y servicio de todos los activos.",
 *     @OA\Response(response="200", description="Devuelve el árbol entero de Dirección, Área y servicio de todos los activos.")
 * )
 */
$app->get('/api/downloadActivosTree', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db_user = new Usuarios(DB_USER);
	$user = $db_user->getUser($token['data']);
	$db = new Activos(DB_SERV);
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Servicios recorridos correctamente";
	$additionalAccess = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
	$activos = $db->getActivosByTipo(42, $user, "All", "All", $additionalAccess);
	$array_paginas = array();
	$array_activos = array();
	$array_archivados = array();
	$array_no_archivados = array();
	foreach ($activos as $activo) {
		$responsables = $db->getPersonas($activo["id"]);
		$familia = $db->getFathersNew($activo["id"]);
		$familiaOrdenada = ordenarFamilia($familia, $activo["id"]);
		$familiaEstructurada = estructurarFamilia($familiaOrdenada);
		foreach ($familiaEstructurada as $ramaFamilia) {
			if ($responsables[0]["product_owner"] != null) {
				$ramaFamilia["Responsable"] = $responsables[0]["product_owner"];
			} else {
				$ramaFamilia["Responsable"] = "Sin responsable";
			}
			$array_activos[] = $ramaFamilia;
			if (isset($ramaFamilia["Organizacion"]) && $ramaFamilia["Organizacion"] == "Telefónica Innovación Digital") {
				if ($activo["archivado"] == 1) {
					$array_archivados[] = $ramaFamilia;
				} else {
					$array_no_archivados[] = $ramaFamilia;
				}
			}
		}
	}
	$array_paginas["todos"] = $array_activos;
	$array_paginas["archivados"] = $array_archivados;
	$array_paginas["no_archivados"] = $array_no_archivados;
	$response_data["paginas"] = $array_paginas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/downloadExample",
 *     tags={"Activos"},
 *     summary="Genera un documento Xslx de ejemplo para importación de activos.",
 *     @OA\Response(response="200", description="Genera un documento Xslx de ejemplo para importación de activos.")
 * )
 */
$app->get('/downloadExample', function (Request $_request, Response $_response) {
	$db = new Activos();
	$tipoActivos = $db->getClaseActivos();
	$db = new Activos(DB_SERV);
	$db_new = new Activos(DB_NEW);
	$ubicaciones = $db->getActivosByTipo(45, null);
	$activos = $db->getActivosByTipo(33, null);
	$activosB2 = $db_new->getClaseActivoById(42);
	$activosB3 = $db_new->getClaseActivoById(33);
	$activosB4 = $db_new->getClaseActivoById(30);
	$activosB5 = $db_new->getClaseActivoById(23);
	$activosB6 = $db_new->getClaseActivoById(18);
	$activosB7 = $db_new->getClaseActivoById(34);
	$numactivos = count($tipoActivos);
	$lista = "Tipos!C$1:C$$numactivos";
	$spreadsheet = new Spreadsheet();
	$spreadsheet->getProperties()->setCreator(NAMETOOL)
		->setTitle('Ejemplo Importación')
		->setSubject('Es un ejemplo para importar un servicio a 11CertTool')
		->setDescription('Se usará este archivo para importar')
		->setKeywords('11Cert Tool');
	$spreadsheet->createSheet();
	$spreadsheet->setActiveSheetIndex(1)
		->fromArray($tipoActivos, null, 'A1')
		->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
	$spreadsheet->getActiveSheet()->setTitle('Tipos');

	$spreadsheet->createSheet();
	$spreadsheet->setActiveSheetIndex(2)
		->fromArray($ubicaciones, null, 'A1')
		->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
	$spreadsheet->getActiveSheet()->setTitle('Ubicacion');

	$spreadsheet->createSheet();
	$spreadsheet->setActiveSheetIndex(3)
		->fromArray($activos, null, 'A1')
		->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
	$spreadsheet->getActiveSheet()->setTitle('Activos');

	$spreadsheet->setActiveSheetIndex(0)
		->setCellValue('A1', strtoupper(NOMBRE))
		->setCellValue('A2', 'Servicio')
		->setCellValue('A3', 'Sistema1')
		->setCellValue('A4', 'Subsistema1')
		->setCellValue('A5', 'Activo1')
		->setCellValue('A6', 'Activo2')
		->setCellValue('A7', 'Activo1 Subsys')
		->setCellValue('B1', "CLASE")
		->setCellValue('B2', $activosB2[0]["nombre"])
		->setCellValue('B3', $activosB3[0]["nombre"])
		->setCellValue('B4', $activosB4[0]["nombre"])
		->setCellValue('B5', $activosB5[0]["nombre"])
		->setCellValue('B6', $activosB6[0]["nombre"])
		->setCellValue('B7', $activosB7[0]["nombre"])
		->setCellValue('C1', 'PADRE')
		->setCellValue('C3', 'Servicio')
		->setCellValue('C4', 'Sistema1')
		->setCellValue('C5', 'Subsistema1')
		->setCellValue('C6', 'Activo1')
		->setCellValue('C7', 'Activo2')
		->setCellValue('D1', 'UBICACIÓN');

	$validacion = new DataValidation();
	$validacion->setType(DataValidation::TYPE_LIST)
		->setErrorStyle(DataValidation::STYLE_INFORMATION)
		->setAllowBlank(false)
		->setShowInputMessage(true)
		->setShowErrorMessage(true)
		->setShowDropDown(true)
		->setErrorTitle(ERROR)
		->setError('Ese activo no está en la lista.')
		->setPromptTitle('Selecciona un tipo de activo de la lista.')
		->setFormula1($lista);
	$spreadsheet->getActiveSheet()->setDataValidation('B2:B100', $validacion);

	$validacionubi = new DataValidation();
	$validacionubi->setType(DataValidation::TYPE_LIST)
		->setErrorStyle(DataValidation::STYLE_INFORMATION)
		->setAllowBlank(false)
		->setShowInputMessage(true)
		->setShowErrorMessage(true)
		->setShowDropDown(true)
		->setErrorTitle(ERROR)
		->setError('Esa ubicación no está en la lista.')
		->setPromptTitle('Selecciona una ubicación de la lista.')
		->setFormula1('Ubicacion!B$1:B$56600');
	$spreadsheet->getActiveSheet()->setDataValidation('D2:D100', $validacionubi);

	$validaciondad = new DataValidation();
	$validaciondad->setType(DataValidation::TYPE_LIST)
		->setErrorStyle(DataValidation::STYLE_INFORMATION)
		->setAllowBlank(false)
		->setShowInputMessage(true)
		->setShowErrorMessage(true)
		->setShowDropDown(true)
		->setErrorTitle(ERROR)
		->setError('Ese activo no está en la lista por lo que se creará uno nuevo.')
		->setPromptTitle('Selecciona un activo de la lista.')
		->setFormula1('Activos!B$1:B$56600');
	$spreadsheet->getActiveSheet()->setDataValidation('A2:A100', $validaciondad);
	$spreadsheet->getActiveSheet()->setDataValidation('C2:C100', $validaciondad);

	$spreadsheet->getActiveSheet()
		->getColumnDimension('A')
		->setAutoSize(true);
	$spreadsheet->getActiveSheet()
		->getColumnDimension('B')
		->setWidth(20);

	$spreadsheet->getActiveSheet()->setTitle('Activos');
	$spreadsheet->setActiveSheetIndex(0);

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header('Content-Disposition: attachment;filename="Import_Activos.xlsx"');
	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
	exit;
});


/**
 * @OA\Get(
 *     path="/api/getCSVCumplimiento",
 *     tags={"Evaluación / ECR"},
 *     summary="Genera un documento CSV plantilla para importar cumplimientos.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del activo que queramos obtener la plantilla.",
 *         @OA\Schema(type="string")
 *     ),
 * 	   @OA\Parameter(
 *         name="normativa",
 *         in="query",
 *         required=true,
 *         description="Normativa de cumplimiento que queramos obtener.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera un documento CSV plantilla para importar cumplimientos.")
 * )
 */ // BLOQUE DE GET API
$app->get('/api/getCSVCumplimiento', function (Request $request, Response $response) {
	global $error;
	$parametros = $request->getQueryParams();
	if (isset($parametros['id']) && !empty($parametros['id']) && is_numeric($parametros['id'])) {
		$id = $parametros['id'];
		$db = new Activos(DB_SERV);
		$activo = $db->getActivo($id);
		$childs = array_merge($db->getTree($activo[0]), $activo);
		$db = new Activos();
		$obligatorios = $db->getClaseActivosObligatorios();
		$childs = array_merge($obligatorios, $childs);
		$eval = $db->getEvaluacion($parametros[NORMATIVA], $childs);
		foreach ($eval as &$elemento) {
			unset($elemento['dominio_ctrls']);
		}
		$spreadsheet = new Spreadsheet();
		$spreadsheet->getProperties()->setCreator(NAMETOOL)
			->setTitle('Ejemplo Importación')
			->setSubject('Es un ejemplo para importar un servicio a 11CertTool')
			->setDescription('Se usará este archivo para importar')
			->setKeywords('11Cert Tool');

		$spreadsheet->createSheet();
		$spreadsheet->setActiveSheetIndex(1)
			->setCellValue('A1', 'Si')
			->setCellValue('A2', 'No')
			->setSheetState(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_VERYHIDDEN);
		$spreadsheet->getActiveSheet()->setTitle('Respuesta');

		$spreadsheet->setActiveSheetIndex(0)
			->setCellValue('A1', 'ID')
			->setCellValue('B1', 'DOMINIO')
			->setCellValue('C1', 'DUDA')
			->setCellValue('D1', 'COD_USF')
			->setCellValue('E1', 'COD-CTRL')
			->setCellValue('F1', 'RESPUESTA')
			->setCellValue('G1', 'COMENTARIO');



		$spreadsheet->setActiveSheetIndex(0)->fromArray($eval, null, 'A2');
		$spreadsheet->getActiveSheet()->setTitle('Cuestionario');

		$validacion = new DataValidation();
		$validacion->setType(DataValidation::TYPE_LIST)
			->setErrorStyle(DataValidation::STYLE_STOP)
			->setAllowBlank(false)
			->setShowInputMessage(true)
			->setShowErrorMessage(true)
			->setShowDropDown(true)
			->setErrorTitle(ERROR)
			->setError('Esa respuesta no está admitida.')
			->setPromptTitle('Selecciona una respuesta de la lista')
			->setFormula1('Respuesta!A$1:A$2');
		$spreadsheet->getActiveSheet()->setDataValidation('F$2:F$56555', $validacion);
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="Nombre_Servicio.xlsx"');
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha facilitado el parámetro ID valido.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Post(
 *     path="/api/clonarEvaluacion",
 *     tags={"Activos"},
 *     summary="Devuelve los logs de las acciones realizadas sobre los activos procesados.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="IdDestino", type="string", description="ID activo destino."),
 *             @OA\Property(property="tipo", type="string", description="ID evaluacion a clonar."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Clona una evaluacion en un activo destino.")
 * )
 */
$app->post('/api/clonarEvaluacion', function (Request $request, Response $response) {
	try {
		$db_operation = new DbOperations(DB_SERV);
		$parametros = $request->getParsedBody();
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "Evaluacion clonada correctamente";
		if (!isset($parametros["IdDestino"]) && !isset($parametros["IdEvaluacion"])) {
			throw new RouteException("No se han facilitado los parámetros necesarios para clonar la evaluación.");
		}
		$id = $parametros['IdEvaluacion'];
		if (strpos($id, 'version-') === 0) {
			$id = str_replace('version-', '', $id);
			$evaluacion = $db_operation->getVersionById($id);
		} else {
			$evaluacion = $db_operation->getEvalById($id);
		}
		if (!isset($evaluacion[0])) {
			throw new RouteException("No se ha encontrado la evaluación o version con ID: " . $id);
		}
		$meta_value = $evaluacion[0]["meta_value"];
		$meta_key = $evaluacion[0]["meta_key"];
		$id_activo = $parametros["IdDestino"];
		$evaluacion = $db_operation->getFechaEvaluaciones($id_activo);
		if (isset($evaluacion[0])) {
			$version = $db_operation->getVersionByEvalId($evaluacion[0]["id"]);
			$version = count($version) + 1;
			$db_operation->insertVersionEvaluacion($evaluacion[0]["id"], $version, "preguntas", $meta_key, $meta_value);
		} else {
			$meta_value = json_decode($meta_value, true);
			$db_operation->setMetaValue($id_activo, $meta_value, $meta_key);
		}
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $e->getMessage();
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/getLogsActivosProcessed",
 *     tags={"Activos"},
 *     summary="Devuelve los logs de las acciones realizadas sobre los activos procesados.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="fecha_inicio", type="string", description="Fecha desde la que queremos empezar a buscar. La fecha tiene que ir en formato AAAA-MM-DD. Valor all para todos los logs desde el principio."),
 *             @OA\Property(property="fecha_final", type="string", description="Fecha hasta la que queremos buscar. La fecha tiene que ir en formato AAAA-MM-DD. Valor all para todos los logs desde el principio."),
 *             @OA\Property(property="tipo", type="string", description="Tipo de logs que queremos obtener. Valores posibles: all, relation_changes, new_activos, deleted_activos, modified_activos."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Devuelve los logs de las acciones realizadas sobre los activos procesados.")
 * )
 */
$app->post('/api/getLogsActivosProcessed', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db_logs = new Logs("octopus_logs");
	$logs = $db_logs->getLogsRelacion();
	$logs = checkdates($logs, $parametros);
	$logs = obtainAllLogs($logs);
	if ($parametros["tipo"] != "all") {
		if ($parametros["tipo"] != "relation_changes") {
			unset($logs["relation_changes"]);
		}
		if ($parametros["tipo"] != "new_activos") {
			unset($logs["new_activos"]);
		}
		if ($parametros["tipo"] != "deleted_activos") {
			unset($logs["deleted_activos"]);
		}
		if ($parametros["tipo"] != "modified_activos") {
			unset($logs["modified_activos"]);
		}
	}
	$logs = processlogs($logs);
	$response->getBody()->write(json_encode($logs, JSON_UNESCAPED_UNICODE));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/getLogsActivosRaw",
 *     tags={"Activos"},
 *     summary="Devuelve los logs de las acciones realizadas sobre los activos.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="fecha_inicio", type="string", description="Fecha desde la que queremos empezar a buscar. La fecha tiene que ir en formato AAAA-MM-DD. Valor all para todos los logs desde el principio."),
 *             @OA\Property(property="fecha_final", type="string", description="Fecha hasta la que queremos buscar. La fecha tiene que ir en formato AAAA-MM-DD. Valor all para todos los logs desde el principio."),
 *             @OA\Property(property="tipo", type="string", description="Tipo de logs que queremos obtener. Valores posibles: all, relation_changes, new_activos, deleted_activos, modified_activos."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Devuelve los logs de las acciones realizadas sobre los activos.")
 * )
 */
$app->post('/api/getLogsActivosRaw', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db_logs = new Logs("octopus_logs");
	$logs = $db_logs->getLogsRelacion();
	$logs = checkdates($logs, $parametros);
	$logs = obtainAllLogs($logs);
	if ($parametros["tipo"] != "all") {
		if ($parametros["tipo"] != "relation_changes") {
			unset($logs["relation_changes"]);
		}
		if ($parametros["tipo"] != "new_activos") {
			unset($logs["new_activos"]);
		}
		if ($parametros["tipo"] != "deleted_activos") {
			unset($logs["deleted_activos"]);
		}
		if ($parametros["tipo"] != "modified_activos") {
			unset($logs["modified_activos"]);
		}
	}
	$response->getBody()->write(json_encode($logs, JSON_UNESCAPED_UNICODE));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getLogsRelacion",
 *     tags={"Activos"},
 *     summary="Devuelve los logs de los cambios de relación",
 *     @OA\Response(response="200", description="Devuelve los logs de los cambios de relación.")
 * )
 */
$app->get('/api/getLogsRelacion', function (Request $_, Response $response) {
	$db_users = new Usuarios("octopus_users");
	$db_logs = new Logs("octopus_logs");
	$db = new Activos(DB_SERV);
	$logs = $db_logs->getLogsRelacion();
	$final_logs = array();
	foreach ($logs as $index => $log) {
		$logs[$index]["id_activo"] = $db->getActivo($log["id_activo"]);
		$logs[$index]["old_padre"] = $db->getActivo($log["old_padre"]);
		$logs[$index]["new_padre"] = $db->getActivo($log["new_padre"]);
		$user = $db_users->getUser($log["id_usuario"]);
		if (isset($user[0])) {
			$user = $user[0]["email"];
		} else {
			$user = "Sin identificar";
		}
		$final_logs[] = $user . " | " . $logs[$index]["fecha"] . " |  El activo " . $logs[$index]["id_activo"][0]["nombre"] . " ha cambiado su relación de " . $logs[$index]["old_padre"][0]["nombre"] . " a " . $logs[$index]["new_padre"][0]["nombre"];
	}
	$response->getBody()->write(json_encode($final_logs, JSON_UNESCAPED_UNICODE));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDatosFormulario",
 *     tags={"Formulario Pentest"},
 *     summary="Obtiene todas las solicitudes de pentest.",
 *     @OA\Response(response="200", description="Obtiene todas las solicitudes de pentest.")
 * )
 */
$app->get('/api/getDatosFormulario', function (Request $_, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;

	// Obtener el ID del usuario
	$db_users = new Usuarios(DB_USER);
	// Obtener los datos del formulario de pentest
	$pentestRequest = new PentestRequest(DB_SERV);
	$pentestData = $pentestRequest->getDatosFormulario();

	$db = new Activos(DB_SERV);
	foreach ($pentestData as &$item) {
		$item["usuario"] = $db_users->getUser($item["user_id"]);
		if (isset($item['usuario'][0])) {
			$item["usuario"] = $item["usuario"][0]["email"];
		} else {
			$item["usuario"] = "Usuario sin identificar. UserId: " . $item["user_id"];
		}
		if (isset($item['id_activo'])) {
			$activo = $db->getActivo($item['id_activo']);
			if (isset($activo[0]['nombre'])) {
				$item['nombre_producto'] = $activo[0]['nombre'];
			} else {
				$item['nombre_producto'] = null;
				error_log("Activo no encontrado para id_activo: " . $item['id_activo']);
			}
		}
	}

	// Añadir el email del usuario a la respuesta
	$response_data['data'] = $pentestData;

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getEvents",
 *     tags={"Eventos"},
 *     summary="Obtiene todos los eventos del calendario.",
 *     @OA\Response(response="200", description="Lista de eventos.")
 * )
 */
$app->get('/api/getEvents', function (Request $_, Response $response) {
	$calendario = new Pentest(DB_SERV);
	$events = $calendario->getEvents();
	$response->getBody()->write(json_encode($events));
	return $response->withHeader('Content-Type', JSON)->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getAssetsWithVulnerabilities",
 *     tags={"Activos"},
 *     summary="Devuelve todos los activos que tienen o han tenido vulnerabilidades.",
 *     @OA\Response(response="200", description="Devuelve todos los activos que tienen o han tenido vulnerabilidades.")
 * )
 */
$app->get('/api/getAssetsWithVulnerabilities', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$vulnerabilidades = $db->getVulnerabilidades();
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = MENSAJE_ACTIVOS_OBTENIDOS;
	try {
		if (empty($vulnerabilidades)) {
			throw new RouteException("No se han encontrado vulnerabilidades.");
		}

		$info_vulns = getVulnsUnicas($vulnerabilidades);

		foreach ($vulnerabilidades as $index => $vulnerabilidad) {
			$issueKey = $vulnerabilidad["vulnerabilidad"];
			if (isset($info_vulns[$issueKey])) {
				$vulnerabilidades[$index]["issue_info"] = $info_vulns[$issueKey];
				$vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["id_prueba"] = $vulnerabilidad["id_prueba"];
				$vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["nombre_prueba"] = $vulnerabilidad["nombre_prueba"];
				$vulnerabilidades[$index]["issue_info"]["pruebaInfo"]["tipo_prueba"] = $vulnerabilidad["tipo_prueba"];
			} else {
				$vulnerabilidades[$index]["issue_info"] = null;
			}
		}

		$array_activos = array();
		foreach ($vulnerabilidades as $index => $vulnerabilidad) {
			$idActivo = $vulnerabilidad["id_activo"];
			$array_activos[$idActivo]["nombre"] = $vulnerabilidad["nombre"];
			$array_activos[$idActivo]["id_activo"] = $idActivo;
			$array_activos[$idActivo]["vulns"][] = $vulnerabilidad["issue_info"];
		}

		$response_data["vulnerabilidades"] = $array_activos;
		$status = 200;
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$status = 400;
		$response_data[MESSAGE] = $e->getMessage();
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus($status);
});

/**
 * @OA\Get(
 *     path="/api/getServiciobySistemaId",
 *     tags={"Activos"},
 *     summary="Recibe un ID de un sistema y devuelve su servicio padre.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del sistema del que queremos saber sus servicios padres.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Recibe un ID de un sistema y devuelve su servicio padre.")
 * )
 */
$app->get('/api/getServiciobySistemaId', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (isset($parametros['id'])) {
		$servicio = getServiciobyActivoId(array("id" => $parametros['id']));
	} else {
		$response_data[ERROR] = true;
	}
	$response_data["servicios"] = $servicio;

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/actualizarExposicion",
 *     tags={"Activos"},
 *     summary="Actualizar la exposición de activos.",
 *     description="Actualiza la exposición de los activos según ciertos criterios. Marca los activos de tipo 42 (Servicio de negocio) como expuestos y sus hijos también.",
 *     @OA\Response(
 *         response=200,
 *         description="Resultado de la actualización de la exposición de activos.",
 *         @OA\JsonContent(
 *             type="string",
 *             description="Mensaje con el número total de activos actualizados."
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor."
 *     )
 * )
 */
$app->get('/api/actualizarExposicion', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);

	$contador = 0;
	$activos = $db->getBiaExpuesto();
	foreach ($activos as $activo) {
		$db->editExposicion($activo["activo_id"], 1);
	}
	$activos = $db->getActivosExposicion();
	foreach ($activos as $activo) {
		if ($activo["activo_id"] == 42 && $activo["expuesto"] == 1) {
			$hijos = $db->getHijos($activo["id"]);
			foreach ($hijos as $hijo) {
				$db->editExposicion($hijo["id"], 1);
				$contador += 1;
			}
		}
	}
	$response_data = "Se han actualizado un total de: " . $contador . " activos.";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getActivosExposicion",
 *     summary="Obtiene los activos de exposición",
 *     description="Devuelve una lista de activos de exposición desde la base de datos.",
 *     tags={"Activos"},
 *     @OA\Response(
 *         response=200,
 *         description="Lista de activos de exposición obtenida exitosamente",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(
 *                 property="Activos",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(
 *                         property="id",
 *                         type="integer",
 *                         description="ID del activo"
 *                     ),
 *                     @OA\Property(
 *                         property="nombre",
 *                         type="string",
 *                         description="Nombre del activo"
 *                     ),
 *                     @OA\Property(
 *                         property="expuesto",
 *                         type="integer",
 *                         description="Indica si el activo está expuesto (1) o no (0)"
 *                     ),
 *                     @OA\Property(
 *                         property="activo_id",
 *                         type="integer",
 *                         description="ID del activo dentro del sistema"
 *                     )
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor"
 *     )
 * )
 */
$app->get('/api/getActivosExposicion', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$response_data["Activos"] = $db->getActivosExposicion();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainAllTypeActivos",
 *     tags={"Activos"},
 *     summary="Devuelve información de todos los tipos de activo que existen.",
 *     @OA\Response(response="200", description="Devuelve información de todos los tipos de activo que existen.")
 * )
 */
$app->get('/api/obtainAllTypeActivos', function (Request $_, Response $response) {
	$db = new DbOperations("octopus_new");
	$activos = $db->obtainAllTypeActivos();
	$response_data[] = $activos;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainFathersActivo",
 *     tags={"Activos"},
 *     summary="Devuelve todos los activos padres que tiene un activo.",
 *     @OA\Response(response="200", description="Devuelve todos los activos padres que tiene un activo.")
 * )
 */
$app->get('/api/obtainFathersActivo', function (Request $request, Response $response) {
	$db = new Activos(DB_SERV);
	$parametros = $request->getQueryParams();
	if ($parametros["simple"] == "false") {
		$padres = $db->getFathersNew($parametros["id"]);
	} else {
		$padres = $db->getFathers($parametros);
	}
	$response_data[] = $padres;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerServiciosUbicacion",
 *     tags={"Activos"},
 *     summary="Devuelve todas las ubicaciones con los servicios que tiene cada una.",
 *     @OA\Response(response="200", description="Devuelve todas las ubicaciones con los servicios que tiene cada una.")
 * )
 */
$app->get('/api/obtenerServiciosUbicacion', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$activos = $db->getActivosByTipo(45);
	$response_data = array();

	foreach ($activos as $activo) {
		$ubicacion = array();
		$ubicacion["id_ubicacion"] = $activo["id"];
		$ubicacion["nombre_ubicacion"] = $activo["nombre"];
		$padres = $db->getFathersNew($activo["id"]);
		foreach ($padres as $padre) {
			if ($padre["tipo"] = "Servicio de Negocio") {
				$ubicacion["servicios"][] = $padre;
			}
		}
		$response_data[] = $ubicacion;
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getHijosTipo",
 *     tags={"Activos"},
 *     summary="Devuelve todos los hijos de un tipo específico de un activo.",
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         required=true,
 *         description="ID del activo del cual queramos obtener sus hijos.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="tipo",
 *         in="query",
 *         required=true,
 *         description="Tipo del que queremos que sean todos los hijos que obtenga.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve todos los hijos de un tipo específico de un activo.")
 * )
 */
$app->get('/api/getHijosTipo', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_SERV);
	if (isset($parametros["nombre"])) {
		$padre = $db->getActivo($parametros["nombre"]);
		$hijos = $db->getHijos($padre[0]["id"]);
	} elseif (isset($parametros["idPadre"])) {
		$hijos = $db->getHijos($parametros["idPadre"]);
	}
	$response_data["Hijos"] = [];
	if (isset($hijos[0])) {
		foreach ($hijos as $activo) {
			if ($activo["tipo"] == $parametros["tipo"]) {
				array_push($response_data["Hijos"], $activo);
			}
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/crearReporterKPMs",
 *    tags={"KPMs"},
 *    summary="Crea un reporter de KPMs.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="userMail", type="string", description="Correo electronico del usuario"),
 * 		@OA\Property(property="Activo", type="integer", description="ID del usuario relacionado."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea un reporter de KPMs.")
 * )
 */
$app->post('/api/crearReporterKPMs', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$token = $request->getAttribute(TOKEN);
	$user_db = new Usuarios(DB_USER);
	$user = $user_db->getUser($token['data']);
	$additional_access = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
	if (!$additional_access || empty($parametros["userID"]) || empty($parametros["activo"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Error.";
	} else {
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "Relación creada correctamente.";
		$db = new KPMs(DB_KPMS);
		$db->crearReporterKPMs($parametros["userID"], $parametros["activo"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/deleteRelacionMarcoNormativa",
 *    tags={"Normativa"},
 *    summary="Elimina una relación del marco normativo.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="idUSF", type="integer", description="Identificador de la relación."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Elimina una relación del marco normativo.")
 * )
 */
$app->post('/api/deleteRelacionMarcoNormativa', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Relación marco normativo eliminada correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["idRelacion"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->deleteRelacionMarcoNormativa($parametros["idRelacion"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/deleteUSF",
 *    tags={"Normativa"},
 *    summary="Elimina un USF.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="idUSF", type="integer", description="Identificador del USF."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Elimina un USF.")
 * )
 */
$app->post('/api/deleteUSF', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "USF eliminado correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["idUSF"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->deleteUSF($parametros["idUSF"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/deleteNormativa",
 *    tags={"Normativa"},
 *    summary="Elimina una normativa.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="idNormativa", type="integer", description="Identificador de la normativa."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Elimina una normativa.")
 * )
 */
$app->post('/api/deleteNormativa', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Normativa correctamente.";
	$db = new Normativas(DB_NEW);
	$db->deleteMarcoFromNormativa($parametros["idNormativa"]);
	$db->deleteControlFromNormativa($parametros["idNormativa"]);
	$db->deleteNormativa($parametros["idNormativa"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/deletePregunta",
 *    tags={"Normativa"},
 *    summary="Elimina una pregunta.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="idPregunta", type="integer", description="Identificador de la pregunta."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Elimina una pregunta.")
 * )
 */
$app->post('/api/deletePregunta', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Pregunta eliminada correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["idPregunta"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->deletePregunta($parametros["idPregunta"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/deleteControl",
 *    tags={"Normativa"},
 *    summary="Elimina un control de la normativa seleccionada.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="idControl", type="integer", description="Identificador del control."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Elimina un control de una normativa.")
 * )
 */
$app->post('/api/deleteControl', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Control eliminado correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["idControl"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->deleteControl($parametros["idControl"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/newUSF",
 *    tags={"Normativa"},
 *    summary="Crea un nuevo USF",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="codigo", type="string", description="Codigo del USF."),
 * 		@OA\Property(property="nombre", type="string", description="Nombre del USF."),
 * 		@OA\Property(property="descripcion", type="string", description="Descripción del USF."),
 * 		@OA\Property(property="dominio", type="string", description="Dominio del USF."),
 * 		@OA\Property(property="idPAC", type="string", description="ID del PAC asociado."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea un nuevo USF.")
 * )
 */
$app->post('/api/newUSF', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Control creado correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["codigo"]) || !isset($parametros["nombre"]) || !isset($parametros["descripcion"]) || !isset($parametros["dominio"]) || !isset($parametros["tipo"]) || !isset($parametros["IdPAC"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->newUSF($parametros["codigo"], $parametros["nombre"], $parametros["descripcion"], $parametros["dominio"], $parametros["tipo"], $parametros["IdPAC"]);
		$usf = $db->getUSFByCodigo($parametros["codigo"]);
		$response_data["USF"] = $usf;
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/newControl",
 *    tags={"Normativa"},
 *    summary="Crea un nuevo control en la normativa seleccionada.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="codigo", type="string", description="Codigo del control."),
 * 		@OA\Property(property="nombre", type="string", description="Nombre del control."),
 * 		@OA\Property(property="descripcion", type="string", description="Descripción del control."),
 * 		@OA\Property(property="dominio", type="string", description="Dominio del control."),
 * 		@OA\Property(property="idNormativa", type="string", description="ID de la normativa asociada."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea un nuevo control de una normativa.")
 * )
 */
$app->post('/api/newControl', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Control creado correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["codigo"]) || !isset($parametros["nombre"]) || !isset($parametros["descripcion"]) || !isset($parametros["dominio"]) || !isset($parametros["idNormativa"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->newControl($parametros["codigo"], $parametros["nombre"], $parametros["descripcion"], $parametros["dominio"], $parametros["idNormativa"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/newPregunta",
 *    tags={"Normativa"},
 *    summary="Crea una nueva pregunta.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="duda", type="string", description="Duda de la pregunta."),
 * 		@OA\Property(property="nivel", type="integer", description="Nivel de la pregunta."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea una nueva pregunta.")
 * )
 */
$app->post('/api/newPregunta', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Normativa creada correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["duda"]) || !isset($parametros["nivel"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->newPregunta($parametros["duda"], $parametros["nivel"]);
		$pregunta = $db->getPreguntaByDuda($parametros["duda"]);
		$pregunta["relacion"] = $db->getRelacionesPregunta($pregunta["id"]);
		$response_data["pregunta"] = $pregunta;
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/editNormativa",
 *    tags={"Normativa"},
 *    summary="Edita una normativa.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="nombre", type="string", description="Nombre de la normativa."),
 * 		@OA\Property(property="enabled", type="boolean", description="Si la normativa está enabled."),
 * 		@OA\Property(property="idNormativa", type="integer", description="ID de la normativa."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea una nueva normativa.")
 * )
 */
$app->post('/api/editNormativa', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Normativa creada correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["nombre"]) || !isset($parametros["enabled"]) || !isset($parametros["idNormativa"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->editNormativa($parametros["nombre"], $parametros["enabled"], $parametros["idNormativa"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/crearRelacionCompleta",
 *     tags={"Normativas"},
 *     summary="Crea una nueva relación de un control.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="infoRelacion", type="string", description="Información de la nueva relación."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Modifica una aplicación de SDLC.")
 * )
 */
$app->post('/api/crearRelacionCompleta', function (Request $request, Response $response) {
	$db = new Normativas(DB_NEW);
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$idControl = $parametros["id"];
	foreach ($parametros["relaciones"] as $relacion) {
		$idUSF = $relacion["idUSF"];
		foreach ($relacion["preguntas"] as $pregunta) {
			$idPregunta = $pregunta["id"];
			$db->newRelacionPreguntaControl($idPregunta, $idControl, $idUSF);
		}
	}
	$response_data[MESSAGE] = "Relacion creada correctamente.";
	$response_data["relaciones"] = $db->getRelacionesControl($idControl);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/crearRelacionPreguntas",
 *    tags={"Normativa"},
 *    summary="Relaciona un control con unas preguntas con USF ya asociado",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="preguntas", type="json", description="Preguntas a añadir."),
 * 		@OA\Property(property="control", type="integer", description="Id del constrol a relacionar."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Relaciona un control con unas preguntas con USF ya asociado")
 * )
 */
$app->post('/api/crearRelacionPreguntas', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Relacion creada correctamente.";
	$preguntas = $parametros["preguntas"];
	$db = new Normativas(DB_NEW);
	foreach ($preguntas as $pregunta) {
		$usfs = $db->getRelacionesPreguntaUSF($pregunta["id"]);
		foreach ($usfs as $usf) {
			$db->newRelacionPreguntaControl($pregunta["id"], $parametros["control"], $usf["id_usf"]);
		}
	}
	$response_data["relaciones"] = $db->getRelacionesControl($parametros["control"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/newNormativa",
 *    tags={"Normativa"},
 *    summary="Crea una nueva normativa.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="nombre", type="string", description="Nombre de la normativa."),
 * 		@OA\Property(property="version", type="integer", description="Versión de la normativa."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Crea una nueva normativa.")
 * )
 */
$app->post('/api/newNormativa', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Normativa creada correctamente.";
	$db = new Normativas(DB_NEW);
	if (!isset($parametros["nombre"]) || !isset($parametros["version"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Faltan parámetros.";
	} else {
		$db->newNormativa($parametros["nombre"], $parametros["version"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDominiosUnicosControles",
 *     tags={"Normativa"},
 *     summary="Devuelve los dominios unicos que tienen los controles.",
 *     @OA\Response(response="200", description="Devuelve los dominios unicos que tienen los controles.")
 * )
 */
$app->get('/api/getDominiosUnicosControles', function (Request $_, Response $response) {
	$db = new Normativas(DB_NEW);
	$dominios = $db->getDominiosControlesUnicos();
	$response_data["dominios"] = $dominios;
	$response_data[MESSAGE] = "Se han obtenido los dominios existentes en los controles.";
	$response_data[ERROR] = false;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getNormativas",
 *     tags={"Normativa"},
 *     summary="Devuelve todas las normativas junto a sus controles.",
 *     @OA\Response(response="200", description="Devuelve todas las normativas junto a sus controles.")
 * )
 */
$app->get('/api/getNormativas', function (Request $_, Response $response) {
	$db = new Normativas(DB_NEW);
	$normativas = $db->getNormativas();
	foreach ($normativas as $index => &$normativa) {
		$normativas[$index]["controles"] = $db->getControlesByNormID($normativa["id"]);
		foreach ($normativas[$index]["controles"] as $indexControl => $control) {
			$normativas[$index]["controles"][$indexControl]["relacion"] = $db->getRelacionesControl($control["id"]);
		}
	}
	$response_data["normativas"] = $normativas;
	$response_data[MESSAGE] = "Se han obtenido todas las normativas con sus controles.";
	$response_data[ERROR] = false;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPreguntas",
 *     tags={"Normativa"},
 *     summary="Devuelve todas las preguntas.",
 *     @OA\Response(response="200", description="Devuelve todas las preguntas.")
 * )
 */
$app->get('/api/getPreguntas', function (Request $_, Response $response) {
	$db = new Normativas(DB_NEW);
	$response_data[ERROR] = false;
	$preguntas = $db->getPreguntas();
	$response_data[MESSAGE] = "Se han obtenido todas las preguntas.";
	foreach ($preguntas as $index => &$pregunta) {
		$preguntas[$index]["relacion"] = $db->getRelacionesPregunta($pregunta["id"]);
	}
	$response_data["Preguntas"] = $preguntas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getUSFs",
 *     tags={"Normativa"},
 *     summary="Devuelve todas las preguntas.",
 *     @OA\Response(response="200", description="Devuelve todas las preguntas.")
 * )
 */
$app->get('/api/getUSFs', function (Request $_, Response $response) {
	$db = new Normativas(DB_NEW);
	$response_data[ERROR] = false;
	$usfs = $db->getUSFs();
	foreach ($usfs as $index => &$usf) {
		$usfs[$index]["relacion"] = $db->getRelacionesUSF($usf["id"]);
	}
	$response_data["USFs"] = $usfs;
	$response_data[MESSAGE] = "Se han obtenido todos los USFs.";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getOrganizaciones",
 *     tags={"Activos"},
 *     summary="Devuelve todas las organizaciones.",
 *     @OA\Response(response="200", description="Devuelve todas las organizaciones.")
 * )
 */
$app->get('/api/getOrganizaciones', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$areas = $db->getActivosByTipo(94);
	$response_data["Organizaciones"] = $areas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDirecciones",
 *     tags={"Activos"},
 *     summary="Devuelve todas las direcciones.",
 *     @OA\Response(response="200", description="Devuelve todas las direcciones.")
 * )
 */
$app->get('/api/getDirecciones', function (Request $request, Response $response) {
	$params = $request->getQueryParams();
	$orgId  = isset($params['organizacionId']) ? (int)$params['organizacionId'] : null;

	if (!$orgId) {
		$payload = ['error' => 'Falta parámetro organizacionId'];
		$response->getBody()->write(json_encode($payload));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	$db = new Activos(DB_SERV);
	$direcciones  = $db->getHijosTipo($orgId, 'Dirección');

	$response_data = ['Direcciones' => $direcciones];
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getAreas",
 *     tags={"Activos"},
 *     summary="Devuelve todas las áreas.",
 *     @OA\Response(response="200", description="Devuelve todas las áreas.")
 * )
 */
$app->get('/api/getAreas', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$areas = $db->getActivosByTipo(123);
	$response_data["Areas"] = $areas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/getPersonasActivo",
 *     tags={"Usuarios"},
 *     summary="Obtiene las personas responsables dado un activo",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Id del activo del cual queremos obtener las personas responsables.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene las personas responsables dado un activo.")
 * )
 */
$app->get('/api/getPersonasActivo', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_SERV);
	if (isset($parametros['id'])) {
		$responsables = $db->getPersonas($parametros['id']);
	}
	$response_data = [];
	$response_data[ERROR] = false;
	if (isset(($responsables[0]))) {
		$response_data["responsables"] = $responsables[0];
	} else {
		$response_data[ERROR] = true;
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getSystemsWithoutProject",
 *     tags={"Activos"},
 *     summary="Obtiene los activos de tipo Sistema de información que no tengan un proyecto en concreto.",
 *     @OA\Parameter(
 *         name="servicio_id",
 *         in="query",
 *         required=true,
 *         description="ID del sistema que queramos comprobar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="proyect_id",
 *         in="query",
 *         required=true,
 *         description="ID del proyecto que queramos buscar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene los activos de tipo Sistema de información que no tengan un proyecto en concreto.")
 * )
 */
$app->get('/api/getSystemsWithoutProject', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_SERV);
	$response_data["sistemas"] = [];
	if (isset($parametros["servicio_id"])) {
		$activos = $db->getTree(array("id" => $parametros["servicio_id"]));
	}
	if (isset($activos)) {
		$sistemas = getActivoByTipo($activos, 33);
	}

	if (isset($parametros["servicio_id"]) && isset($parametros["proyect_id"])) {
		foreach ($sistemas as $sistema) {
			$sistemas = $db->findPac($parametros["proyect_id"], $sistema["id"]);
			if (count($sistemas) == 0) {
				array_push($response_data["sistemas"], $sistema);
			}
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getKpms",
 *     tags={"Kpms"},
 *     summary="Devuelve los datos de madurez y métricas de Kpms reportados por los usuarios.",
 *     @OA\Response(response="200", description="Devuelve los datos de madurez y métricas de Kpms reportados por los usuarios.")
 * )
 */
$app->get('/api/getKpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	if (isset($token["data"])) {
		$db = new Usuarios(DB_USER);
		$user = $db->getUser($token['data']);
		$additionalAccess = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
		$db = new Activos(DB_KPMS);
		$metricas = $db->getmetricasbyuser($token["data"], $additionalAccess);
		$madurez = $db->getmadurezbyuser($token["data"], $additionalAccess);
		$csirt = $db->getMetricasCsirtByUser($token["data"], $additionalAccess);
	}
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data["metricas"] = $metricas;
	$response_data["madurez"] = $madurez;
	$response_data["csirt"] = $csirt;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainPreguntasKpmsCsirt",
 *     tags={"Kpms"},
 *     summary="Devuelve los datos de las preguntas de los KPMs.",
 *     @OA\Response(response="200", description="Devuelve los datos de las preguntas de los KPMs.")
 * )
 */
$app->get('/api/obtainPreguntasKpmsCsirt', function (Request $_, Response $response) {
	$db = new Activos(DB_KPMS);
	$response_data = $db->getPreguntasKpmsCsirt();
	$response->getBody()->write(json_encode($response_data, JSON_UNESCAPED_UNICODE));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainPreguntasKpms",
 *     tags={"Kpms"},
 *     summary="Devuelve los datos de las preguntas de los KPMs.",
 *     @OA\Response(response="200", description="Devuelve los datos de las preguntas de los KPMs.")
 * )
 */
$app->get('/api/obtainPreguntasKpms', function (Request $request, Response $response) {
	$db = new Activos(DB_KPMS);
	$parametros = $request->getQueryParams();
	if (!isset($parametros["type"])) {
		$response_data = $db->getPreguntasKpmsFormulario();
	} else {
		$response_data = $db->getAllPreguntasKpms();
	}
	$response->getBody()->write(json_encode($response_data, JSON_UNESCAPED_UNICODE));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getLastKpms",
 *     tags={"Kpms"},
 *     summary="Devuelve los últimos valores de reportes de métricas y madurez.",
 *     @OA\Response(response="200", description="Devuelve los últimos valores de reportes de métricas y madurez.")
 * )
 */
$app->get('/api/getLastKpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_KPMS);
	$response_data = $db->getLastReportKpms($parametros, $token["data"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getReportAsCsirt",
 *     tags={"Kpms"},
 *     summary="Obtiene los activos que pueden ser reportados para KPMS por Csirt.",
 *     @OA\Response(response="200", description="Obtiene los activos que pueden ser reportados para KPMS por Csirt.")
 * )
 */
$app->get('/api/getReportAsCsirt', function (Request $_, Response $response) {
	$db = new Activos(DB_KPMS);
	$activos = $db->getReportAsCsirt();
	$trimestre_actual = obtenerTrimestre(date('Y-m-d'));
	$response_data[ERROR] = false;
	$activosresult = array();
	$db = new Activos(DB_SERV);
	foreach ($activos as $activo) {
		$result = $db->getActivo($activo["activo_id"]);
		if (isset($result[0])) {
			$reported = reportedInThisQ($result[0]["nombre"], $trimestre_actual);
			$result[0]["Reported"] = $reported;
			array_push($activosresult, $result[0]);
		}
	}
	$response_data["reporte"] = $activosresult;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getReportAs",
 *     tags={"Kpms"},
 *     summary="Obtiene los activos que pueden ser reportados para KPMS por el usuario (reporter).",
 *     @OA\Response(response="200", description="Obtiene los activos que pueden ser reportados para KPMS por el usuario (reporter).")
 * )
 */
$app->get('/api/getReportAs', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Activos(DB_KPMS);
	$activos = $db->getReportAs($token["data"], $request->getUri()->getPath());
	$response_data[ERROR] = false;
	$activosresult = array();
	$db = new Activos(DB_SERV);
	foreach ($activos as $activo) {
		$result = $db->getActivo($activo["activo_id"]);
		if (isset($result[0])) {
			array_push($activosresult, $result[0]);
		}
	}
	$response_data["reporte"] = $activosresult;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getClaseActivos",
 *     tags={"Activos"},
 *     summary="Devuelve el tipo de un activo dado.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve el tipo de un activo dado.")
 * )
 */
$app->get('/api/getClaseActivos', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_SERV);
	if (isset($parametros['id'])) {
		$activo = $db->getActivo($parametros['id']);
	}
	$db = new Activos;
	$activos = $db->getClaseActivos();
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[TOTAL] = count($activos);
	$response_data[CLASE_ACTIVOS] = $activos;
	if (isset($activo[0])) {
		$response_data['activoSelect'] = $activo[0];
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPreguntasEvaluacion",
 *     tags={"Evaluaciones"},
 *     summary="Devuelve las evaluaciones de un sistema.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la evaluacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve las preguntas de una evaluacion dada.")
 * )
 */
$app->get('/api/getPreguntasEvaluacion', function (Request $request, Response $response) {
	$response_data = [
		ERROR => false,
		MESSAGE => 'Preguntas devueltas con éxito'
	];

	try {
		$parametros = $request->getQueryParams();
		if (!isset($parametros['id']) || empty($parametros['id'])) {
			throw new InvalidArgumentException('Falta el id de la evaluación o está vacío');
		}
		$id = $parametros['id'];
		$db_operation = new DbOperations(DB_SERV);

		// Determinar el tipo de evaluación y obtenerla
		if (strpos($id, 'version-') === 0) {
			$id = str_replace('version-', '', $id);
			$evaluacion = $db_operation->getVersionById($id);
		} else {
			$evaluacion = $db_operation->getEvalById($id);
		}

		if (!isset($evaluacion[0])) {
			throw new RouteException('Evaluación no encontrada.', 404);
		}

		// Procesar la evaluación
		$evaluacion = $evaluacion[0];
		$evaluacion_limpia = [];

		// Decodificar la evaluación
		$meta_value = json_decode($evaluacion["meta_value"], true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RouteException('Error al decodificar datos de evaluación: ' . json_last_error_msg());
		}

		$evaluacion["meta_value"] = $meta_value;

		// Procesar cada pregunta en la evaluación
		foreach ($evaluacion["meta_value"] as $key => &$respuesta) {
			$pregunta_escrita = $db_operation->getPreguntasById($key);

			// Formatear la respuesta
			if ($respuesta == 1) {
				$respuesta = "SI";
			} elseif ($respuesta == 0) {
				$respuesta = "NO";
			}

			$pregunta_escrita[0]["respuesta"] = $respuesta;
			$pregunta_escrita[0]["id"] = $key;
			$pregunta_escrita[0]["id_eval"] = $evaluacion["id"];
			$pregunta_escrita[0]["version"] = false;
			array_push($evaluacion_limpia, $pregunta_escrita[0]);
		}

		$evaluacion["meta_value"] = $evaluacion_limpia;
		$response_data["Evaluacion"] = $evaluacion;
		$status = 200;
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$status = 400;
		$response_data[MESSAGE] = $e->getMessage();
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus($status);
});

/**
 * @OA\Get(
 *     path="/api/getEvaluacionesSistema",
 *     tags={"Evaluaciones"},
 *     summary="Devuelve las evaluaciones de un sistema.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del sistema.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve las evaluaciones de un sistema.")
 * )
 */
$app->get('/api/getEvaluacionesSistema', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (isset($parametros['id']) && !empty($parametros['id']) && is_numeric($parametros['id'])) {
		$id = $parametros['id'];
		$db = new Activos(DB_SERV);
		$evaluaciones = $db->getEvalByActivoId($id, "preguntas");
		if (isset($evaluaciones[0])) {
			$evaluaciones[0]["version"] = false;
			$evaluaciones_versiones = $db->getVersionesEvaluacion($evaluaciones[0]["id"]);
			foreach ($evaluaciones_versiones as $version) {
				array_push($evaluaciones, $version);
			}
			$response_data["Evaluaciones"] = $evaluaciones;
		} else {
			$response_data["Evaluaciones"] = array();
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Falta el id del servicio en la solicitud o no es valido.';
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});
/**
 * @OA\Get(
 *     path="/api/getEvalServicio",
 *     tags={"Evaluaciones"},
 *     summary="Devuelve la evaluación de un servicio dada una normativa.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del servicio.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="normativa",
 *         in="query",
 *         required=true,
 *         description="ID de la normativa.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve la evaluación de un servicio dada una normativa.")
 * )
 */
$app->get('/api/getEvalServicio', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) && empty($parametros['id']) && !is_numeric($parametros['id'])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}

	if (!isset($parametros[NORMATIVA]) && empty($parametros[NORMATIVA])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Faltan el parametro de normativa requerido.", 400);
	}
	$id = $parametros['id'];
	$dbServ = new Activos(DB_SERV);
	$activo = $dbServ->getActivo($id);
	if (!isset($activo[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no existe.", 400);
	}
	$db = new Activos();
	$childs = [...$dbServ->getTree($activo[0]), $activo[0]];
	$obligatorios = getActivosObligatoriosSinRepetir($childs);
	$childs = [...$obligatorios, ...$childs];
	$response_data[PREGUNTAS] = $db->getEvaluacion($parametros[NORMATIVA], $childs);
	$response_data[ERROR] = false;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getEvalNoEvaluados",
 *     tags={"Evaluaciones"},
 *     summary="Obtiene un cuestionario parcial de los controles que no se han evaluado en un inicio.",
 *     @OA\Parameter(
 *         name="norma",
 *         in="query",
 *         required=true,
 *         description="ID de la normativa.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="Direcciones",
 *         in="query",
 *         required=true,
 *         description="ID del activo del cual queramos obtener sus hijos.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="Direcciones",
 *         in="query",
 *         required=true,
 *         description="ID del activo del cual queramos obtener sus hijos.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene un cuestionario parcial de los controles que no se han evaluado en un inicio.")
 * )
 */
$app->get('/api/getEvalNoEvaluados', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (isset($parametros['norma']) && isset($parametros['fecha'])) {
		$norma = $parametros['norma'];
		$fecha = $parametros['fecha'];
		$idVersion = $parametros['idVersion'];
		$db = new Activos(DB_SERV);
		$activo = $db->getIdActivoEvaluacionByFecha($fecha);
		if (isset($activo[0])) {
			$activo = $db->getActivo($activo[0]["id"]);
			$childs = [...$db->getTree($activo[0]), $activo[0]];
			$obligatorios = getActivosObligatoriosSinRepetir($childs);
			$childs = [...$obligatorios, ...$childs];
			$db = new Activos();
			$preguntasnuevas = $db->getEvaluacion($norma, $childs);
			$preguntasantiguas = $db->getPreguntasEvaluacionByFecha($fecha);
			if ($idVersion !== "null") {
				$preguntasantiguas = $db->getPreguntasVersionByFecha($idVersion);
			}
			if (isset($preguntasantiguas[0])) {
				$preguntasantiguas = json_decode($preguntasantiguas[0]["preguntas"], true);
				$response_data["preguntas"] = eliminarPreguntasExistentes($preguntasnuevas, $preguntasantiguas);
			}
		}
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Falta el id del servicio en la solicitud o no es valido.';
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Get(
 *     path="/api/getFechasEvaluacion",
 *     tags={"Evaluaciones"},
 *     summary="Devuelve todas las fechas de una evaluación dada.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve todas las fechas de una evaluación dada.")
 * )
 */
$app->get('/api/getFechasEvaluacion', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) && empty($parametros['id']) && !is_numeric($parametros['id'])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}
	$id = $parametros['id'];
	$db = new DbOperations(DB_SERV);
	$fechas = $db->getFechaEvaluaciones($id);
	if (!isset($fechas[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "No se han encontrado fechas para la evaluación.", 400);
	}
	foreach ($fechas as $key => $fecha) {
		$fechas[$key]["version"] = $db->getVersionesEvaluacion($fecha["id"]);
	}
	$response_data['fechas'] = $fechas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getBia",
 *     tags={"Evaluación / EAE"},
 *     summary="Devuelve un Bia de un activo dado.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve un Bia de un activo dado.")
 * )
 */
$app->get('/api/getBia', function (Request $request, Response $response) {
	global $error;
	$parametros = $request->getQueryParams();
	if (!isset($parametros["id"])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}
	$id = $parametros["id"];
	$db = new DbOperations(DB_SERV);
	$bia = $db->getBia($id);
	if (!isset($bia[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "No hay ningun BIA guardado para el activo.", 400);
	}
	$resultadobia["bia"] = calcularBia($bia);
	if ($bia[0]["email"] != null && $bia[0]["email"] != "") {
		$resultadobia["bia"]["email"] = $bia[0]["email"];
	} else {
		$resultadobia["bia"]["email"] = "";
	}
	$response->getBody()->write(json_encode($resultadobia));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getUsers",
 *     tags={"Usuarios"},
 *     summary="Devuelve información del usuario que llama a la petición.",
 *     @OA\Response(response="200", description="Devuelve información del usuario que llama a la petición.")
 * )
 */
$app->get('/api/getUsers', function (Request $_, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$db = new Usuarios(DB_USER);
	$response_data["usuarios"] = $db->getUsers();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getUser",
 *     tags={"Usuarios"},
 *     summary="Devuelve información del usuario que llama a la petición.",
 *     @OA\Response(response="200", description="Devuelve información del usuario que llama a la petición.")
 * )
 */
$app->get('/api/getUser', function (Request $request, Response $response) {
	try {
		$response_data = [];
		$response_data[ERROR] = false;
		$parameters = $request->getQueryParams();
		if (!isset($parameters["id"]) || empty($parameters["id"]) || !is_numeric($parameters["id"])) {
			throw new RouteException('Falta el id del usuario en la solicitud o no es valido.');
		}
		$db = new Usuarios(DB_USER);
		$response_data["usuario"] = $db->getUser($parameters["id"]);
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Error al obtener el usuario: ' . $e->getMessage();
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getRoles",
 *     tags={"Roles"},
 *     summary="Devuelve todos los roles existentes.",
 *     @OA\Response(response="200", description="Devuelve todos los roles existentes.")
 * )
 */
$app->get('/api/getRoles', function (Request $_, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$db = new Usuarios(DB_USER);
	$response_data["roles"] = $db->getRoles();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getEndpointsByRole",
 *     tags={"Roles"},
 *     summary="Devuelve todos los endpoints de un rol específico.",
 *    @OA\Parameter(
 * 	   name="id",
 * 	   in="query",
 * 	   required=true,
 * 	   description="ID del rol.",
 * 	   @OA\Schema(type="string")
 *   ),
 *    @OA\Parameter(
 * 	   name="includeAll",
 * 	   in="query",
 * 	   required=false,
 * 	   description="Si es true, incluye todos los endpoints, no solo los asignados al rol.",
 * 	   @OA\Schema(type="boolean")
 *   ),
 *    @OA\Response(response="200", description="Devuelve todos los endpoints de un rol específico.")
 * )
 */
$app->get('/api/getEndpointsByRole', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parameters = $request->getQueryParams();
	$db = new Usuarios(DB_USER);
	$includeAll = isset($parameters["includeAll"]) ? filter_var($parameters["includeAll"], FILTER_VALIDATE_BOOLEAN) : false;
	$response_data["endpoints"] = $db->getEndpointsByRole($parameters["id"], $includeAll);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getEndpoints",
 *     tags={"Endpoints"},
 *     summary="Devuelve todos los endpoints existentes.",
 *     @OA\Response(response="200", description="Devuelve todos los endpoints existentes.")
 * )
 */
$app->get('/api/getEndpoints', function (Request $_, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$db = new Usuarios(DB_USER);
	$response_data["endpoints"] = $db->getEndpoints();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getHistoryServicio",
 *     tags={"Evaluaciones"},
 *     summary="Obtiene el resultado del calculo de la evaluación para poder dibujar las graficas o mostrar los valores.",
 *     @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de los activos a evaluar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="version",
 *         in="query",
 *         required=false,
 *         description="Versión exacta de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene el resultado del calculo de la evaluación para poder dibujar las graficas o mostrar los valores.")
 * )
 */
$app->get('/api/getHistoryServicio', function ($request, $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) || empty($parametros['id']) || !is_numeric($parametros['id']) || !isset($parametros[FECHA])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Falta el id del servicio en la solicitud o no es valido.';
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}

	$db = new Activos(DB_SERV);
	$activos = getActivosParaEvaluacion($parametros["id"]);

	if ($parametros[FECHA] !== 'null') {
		if (isset($parametros["version"])) {
			$preguntas = $db->getPreguntasVersionByFecha($parametros["version"]);
		} else {
			$preguntas = $db->getPreguntasEvaluacionByFecha($parametros[FECHA]);
		}

		if ($preguntas[0] !== false) {
			$preguntas = json_decode($preguntas[0]["preguntas"], true);
			$preguntas = prepararPreguntas($preguntas, $parametros["id"]);
			$comparacion = isset($preguntas['3ps']) ? getCompararNormativa($db, "3ps", $activos, $preguntas) : getCompararNormativa($db, "all", $activos, $preguntas);
			unset($comparacion["preguntas"]);
			$response_data['eval'] = $comparacion;
			$response->getBody()->write(json_encode($response_data));
		}
		return $response->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Get(
 *     path="/api/getSeguimientoByPacID",
 *     tags={"Evaluación / PAC"},
 *     summary="Obtiene el listado completo de Pacs que se han generado por el análisis de riesgo.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Versión exacta de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene el listado completo de Pacs que se han generado por el análisis de riesgo.")
 * )
 */
$app->get('/api/getSeguimientoByPacID', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id'])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}
	$response_data = [];
	$response_data[ERROR] = false;
	$token = $request->getAttribute(TOKEN);
	$db_users = new Usuarios(DB_USER);
	$user = $db_users->getUser($token['data']);
	$dates = $db_users->getDateAcceso($user[0]["id"]);
	$db_serv = new Activos(DB_SERV);
	$seguimientos = $db_serv->getSeguimientoByPacId($parametros['id']);
	if (!isset($seguimientos[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "No se han encontrado seguimientos para el PAC.", 400);
	}
	foreach ($seguimientos as $index => $seguimiento) {
		$activo = $db_serv->getActivo($seguimiento["activo_id"]);
		if ($activo[0]["archivado"] == 1) {
			$seguimientos[$index]["Archivado"] = true;
			continue;
		} else {
			$seguimientos[$index]["Archivado"] = false;
		}
		$padres = $db_serv->getFathersNewByTipo($seguimiento["activo_id"], "Servicio de Negocio");
		if (isset($padres[0])) {
			$seguimientos[$index]["servicio"] = $padres[0]["nombre"];
			$seguimientos[$index]["servicio_id"] = $padres[0]["id"];
			$bia = $db_serv->getBia($padres[0]["id"]);
			$resultadobia = calcularBia($bia);
			if (isset($resultadobia["Dis"])) {
				$seguimientos[$index]["BIA"] = traducirBia($resultadobia["Dis"]);
			} else {
				$seguimientos[$index]["BIA"] = "Bia sin calcular";
			}
		} else {
			$seguimientos[$index]["servicio"] = "Sin Servicio";
			$seguimientos[$index]["BIA"] = "Bia sin calcular";
		}
		$eval = $db_serv->getFechaEvaluacionesById($seguimiento["evaluacion_id"], "all");
		if (isset($eval[0])) {
			$seguimientos[$index]["fecha_evaluacion"] = $eval[0]["fecha"];
			$dateEvaluacion = new DateTime($eval[0]["fecha"]);
			if (isset($dates[0]["continuidad_last_date"])) {
				$dateContinuidad = new DateTime($dates[0]["continuidad_last_date"]);
				if ($dateContinuidad < $dateEvaluacion) {
					$seguimientos[$index]["Alert"] = true;
				} else {
					$seguimientos[$index]["Alert"] = false;
				}
			} else {
				$seguimientos[$index]["Alert"] = false;
			}
		} else {
			$seguimientos[$index]["fecha_evaluacion"] = "Fecha no disponible";
			$seguimientos[$index]["Alert"] = false;
		}
	}
	$response_data = $seguimientos;

	if (isset($parametros["modulo"]) && $parametros["modulo"] == "continuidad") {
		$db_users->setDateAccesoContinuidad($user[0]["id"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/getDatosBia",
 *     tags={"Evaluación / EAE"},
 *     summary="Devuelve los datos de BIA.",
 *     @OA\Response(response="200", description="Devuelve los datos de BIA.")
 * )
 */
$app->get('/api/getDatosBia', function (Request $_, Response $response) {
	global $error;
	$response_data = [];
	$response_data[ERROR] = false;

	$db_new = new DbOperations(DB_NEW);
	$db_serv = new DbOperations(DB_SERV);
	$db_serv2 = new Activos(DB_SERV);

	$preguntasBia = $db_new->getPreguntasBIA();
	$bia = $db_serv->getAllBia();
	if (!isset($bia[0])) {
		return ERRORRESPONDER->createErrorResponse($_->getUri()->getPath(), "No se han encontrado ningun BIA.", 400);
	}
	$activos = $db_serv2->getActivos(null, true);

	$activosMap = [];
	foreach ($activos as $activo) {
		$familia = $db_serv2->getFathersNew($activo["id"]);
		$familiaOrdenada = ordenarFamilia($familia, $activo["id"]);
		$familiaEstructurada = estructurarFamilia($familiaOrdenada);
		foreach ($familiaEstructurada as $ramaFamilia) {
			if (isset($activo['id'], $activo['nombre'], $activo['archivado']) && $activo['archivado'] == 0 && (($ramaFamilia["Organizacion"]) && $ramaFamilia["Organizacion"] == "Telefónica Innovación Digital")) {
				$activosMap[$activo['id']] = $activo['nombre'];
				$array_archivados[] = $ramaFamilia;
			}
		}
	}

	try {
		$tabla = [];
		foreach ($bia as $item) {
			$bia_decode = json_decode($item["meta_value"], true);
			$id_bia = $item["id"];
			$activobia = $bia_decode["id"];

			if (!isset($activosMap[$activobia])) {
				continue;
			}

			$nombreActivo = $activosMap[$activobia] ?? 'Activo no encontrado';

			$fila = [
				'id_bia' => $id_bia,
				'activobia' => $activobia,
				'nombreActivo' => $nombreActivo,
				'pregunta8' => null,
				'respuesta8' => null,
				'pregunta9' => null,
				'respuesta9' => null,
				'pregunta10' => null,
				'respuesta10' => null,
			];

			foreach ($preguntasBia as $secondItem) {
				$id_pregunta = $secondItem["id"];
				$respuestas_decode = json_decode($secondItem["respuestas"], true);

				if (in_array($id_pregunta, [8, 9, 10])) {
					$indice_respuesta = $bia_decode[$id_pregunta] ?? null;
					$respuesta = $respuestas_decode[$indice_respuesta] ?? null;

					if ($id_pregunta == 8) {
						$fila['pregunta8'] = $secondItem["duda"];
						$fila['respuesta8'] = $respuesta;
					} elseif ($id_pregunta == 9) {
						$fila['pregunta9'] = $secondItem["duda"];
						$fila['respuesta9'] = $respuesta;
					} elseif ($id_pregunta == 10) {
						$fila['pregunta10'] = $secondItem["duda"];
						$fila['respuesta10'] = $respuesta;
					}
				}
			}
			$tabla[] = $fila;
		}
		$response_data['tabla'] = $tabla;
	} catch (Exception $e) {
		return ERRORRESPONDER->createErrorResponse($_->getUri()->getPath(), "Error al procesar los datos de BIA: " . $e->getMessage(), 500);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getListPac",
 *     tags={"Evaluación / PAC"},
 *     summary="Obtiene el listado completo de Pacs que se han generado por el análisis de riesgo.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Versión exacta de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene el listado completo de Pacs que se han generado por el análisis de riesgo.")
 * )
 */
$app->get('/api/getListPac', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) && empty($parametros['id']) && !is_numeric($parametros['id'])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}

	$response_data = [];
	$response_data[ERROR] = false;
	$id = $parametros['id'];
	$db = new Activos(DB_SERV);
	$activo = $db->getActivo($id);
	if (!isset($activo[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no existe.", 400);
	}
	if (isset($activo[0]["id"]) & isset($activo[0]["tipo"]) & $activo[0]["tipo"] !== 42) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no es del tipo servicio de negocio.", 400);
	}
	$activos = $db->getHijos($activo[0]["id"]);
	$sistemas = getActivoByTipo($activos, 33, "tipo_id");
	$seguimientos = [];
	sort($sistemas);
	foreach ($sistemas as $sistema) {
		updateListPac($sistema["id"]);
		$seguimientosPac = $db->getSeguimientoByActivoId($sistema["id"]);
		if (isset($seguimientosPac[0])) {
			$activosSistema = getActivosParaEvaluacion($seguimientosPac[0]["activo_id"]);
			$fecha = getLastfecha($db, $seguimientosPac[0]["activo_id"]);
			if ($fecha["fecha"] != NO_EVALUADO) {
				$preguntas = $db->getPreguntasVersionByFecha($fecha["id"]);
				if (!isset($preguntas[0])) {
					$preguntas = $db->getPreguntasEvaluacionByFecha($fecha["id"]);
				}
				if (isset($preguntas[0][PREGUNTAS])) {
					$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
					$preguntas = prepararPreguntas($preguntas, $seguimientosPac[0]["activo_id"]);
				}
				$eval = getEvaluacionActivos($activosSistema, $preguntas);

				if (isset($eval["pac"])) {
					foreach ($seguimientosPac as &$seguimiento) {
						if (isset($eval["pac"][$seguimiento["codpac"]]["prioridad"])) {
							$prioridad = $eval["pac"][$seguimiento["codpac"]]["prioridad"];
							$seguimiento["prioridad"] = $prioridad;
						}
					}
				}
			}
		}
		array_push($seguimientos, $seguimientosPac);
	}
	$response_data['seguimientos'] = $seguimientos;
	$response_data['sistemas'] = $sistemas;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getProyectos",
 *     tags={"Evaluación / PAC"},
 *     summary="Devuelve todos los proyectos.",
 *     @OA\Response(response="200", description="Devuelve todos los proyectos.")
 * )
 */
$app->get('/api/getProyectos', function (Request $_, Response $response) {
	$db = new Activos();
	$proyectos = $db->getProyectos();
	$resultadobia["proyectos"] = $proyectos;
	$response->getBody()->write(json_encode($resultadobia));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getKiuwanAplication",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve una aplicacion de Kiuwan filtrando por su nombre.",
 *     @OA\Response(response="200", description="Devuelve una aplicacion de Kiuwan filtrando por su nombre.")
 * )
 */
$app->get('/api/getKiuwanAplication', function (Request $_, Response $response) {
	try {
		$db = new Pentest(DB_SERV);
		$kiuwanData = $db->getKiuwanData();

		if (isset($kiuwanData['error']) && $kiuwanData['error']) {
			throw new RouteException($kiuwanData['message']);
		}

		$response->getBody()->write(json_encode($kiuwanData));

		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(200);
	} catch (Exception $e) {
		error_log($e->getMessage());
		$errorResponse = [
			'error' => true,
			'message' => $e->getMessage(),
		];

		$response->getBody()->write(json_encode($errorResponse));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(500);
	}
});

/**
 * @OA\Get(
 *     path="/api/getKiuwanApps",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve todas las aplicaciones de Kiuwan.",
 *     @OA\Response(response="200", description="Devuelve todas las aplicaciones de Kiuwan.")
 * )
 */
$app->get('/api/getKiuwanApps', function (Request $_, Response $response) {
	try {
		$kiuwan = new Kiuwan();
		$applications = $kiuwan->getApplications('/applications/list');

		if (!$applications) {
			throw new RouteException('No se pudieron obtener las aplicaciones de Kiuwan.');
		}

		// Preparar los endpoints para obtener el análisis de cada aplicación
		$endpoints = [];
		foreach ($applications as $app) {
			$endpoints[$app['name']] = '/applications/last_analysis?application=' . urlencode($app['name']);
		}

		// Realizar las solicitudes en paralelo
		$analyses = $kiuwan->executeMultiCurlRequests($endpoints);

		$db = new Pentest(DB_SERV);
		foreach ($applications as $app) {
			// Verificar y formatear la fecha de creación de la aplicación
			$formatted_date = null;
			if (isset($app['creationDate']) && !empty($app['creationDate'])) {
				$creation_date = DateTime::createFromFormat(DateTimeInterface::ATOM, $app['creationDate']);
				if ($creation_date !== false) {
					$formatted_date = $creation_date->format('Y-m-d H:i:s');
				}
			}

			// Verificar y formatear las fechas de "lastSuccessfulBaseline" y "lastSuccessfulDelivery"
			$baseline_date = isset($app['lastSuccessfulBaseline']['creationDate'])
				? new DateTime($app['lastSuccessfulBaseline']['creationDate'])
				: null;

			$delivery_date = isset($app['lastSuccessfulDelivery']['creationDate'])
				? new DateTime($app['lastSuccessfulDelivery']['creationDate'])
				: null;

			// Inicializar la variable que contendrá la fecha más reciente
			$analysis_date = null;

			// Comparar las fechas si ambas están definidas
			if ($baseline_date && $delivery_date) {
				// Comparar y obtener la más reciente
				$analysis_date = ($baseline_date > $delivery_date)
					? $baseline_date->format('Y-m-d H:i:s')
					: $delivery_date->format('Y-m-d H:i:s');
			} elseif ($baseline_date) {
				// Si solo está definida la fecha de la línea base
				$analysis_date = $baseline_date->format('Y-m-d H:i:s');
			} elseif ($delivery_date) {
				// Si solo está definida la fecha de la entrega
				$analysis_date = $delivery_date->format('Y-m-d H:i:s');
			}

			// Obtener el análisis correspondiente
			$analysis = $analyses[$app['name']] ?? null;

			// Preparar los datos para insertar en la base de datos
			$data = [
				'app_name' => $app['name'],
				'creation_date' => $formatted_date, // Insertar NULL si la fecha es nula o vacía
				'code' => $app['lastSuccessfulBaseline']['code'] ?? null,
				'analysis_code' => $analysis['analysisCode'] ?? null,
				'analysis_url' => $analysis['analysisURL'] ?? null,
				'analysis_date' => $analysis_date // Insertar la fecha más reciente
			];

			// Insertar o actualizar los datos en la base de datos
			$db->insertKiuwanData($data);
		}

		// Responder con la lista de aplicaciones
		$response->getBody()->write(json_encode($applications));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(200);
	} catch (Exception $e) {
		// Log del error
		error_log($e->getMessage());

		// Respuesta de error
		$errorResponse = [
			'error' => true,
			'message' => $e->getMessage(),
		];

		$response->getBody()->write(json_encode($errorResponse));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(500);
	}
});

/**
 * @OA\Get(
 *     path="/api/getPrismaCloud",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve todas las cloud de PrismaCloud.",
 *     @OA\Response(response="200", description="Devuelve todas las cloud de PrismaCloud.")
 * )
 */
$app->get('/api/getPrismaCloud', function (Request $_, Response $response) {
	$prisma = new PrismaCloudAPI();
	$cloud = $prisma->getPrismaCloud();
	$cloudArray = json_decode($cloud);

	if (!is_array($cloudArray)) {
		$cloudArray = [$cloudArray];
	}

	$revision = new Revision(DB_SERV);

	foreach ($cloudArray as &$cloudObj) {
		$check = $revision->checkSuscriptionHasActivos($cloudObj->accountId);
		$cloudObj->asociacion = $check["asociacion"];
		$cloudObj->hasReview = $revision->existsReviewForSuscription($cloudObj->accountId);
	}

	$resultadobia["cloud"] = $cloudArray;
	$response->getBody()->write(json_encode($resultadobia));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getRelacionSuscripcion",
 *     tags={"PrismaCloud"},
 *	   @OA\Parameter(
 *         name="idSuscripcion",
 *         in="query",
 *         required=true,
 *         description="ID de la suscripción.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Devuelve si una suscripción tiene relación con algún activo de 11Cert.",
 *     @OA\Response(response="200", description="Devuelve si una suscripción tiene relación con algún activo de 11Cert.")
 * )
 */
$app->get('/api/getRelacionSuscripcion', function (Request $request, Response $response) {
	$db = new Revision(DB_SERV);
	$parametros = $request->getQueryParams();
	$responseData[ERROR] = false;
	$activo = $db->getActivoBySusId($parametros["idSuscripcion"]);
	if (empty($activo)) {
		$responseData["relation"] = false;
	} else {
		$responseData["relation"] = true;
		$responseData["activo"] = $activo[0];
	}
	$response->getBody()->write(json_encode($responseData));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPrismaSusInfo",
 *     tags={"PrismaCloud"},
 *	   @OA\Parameter(
 *         name="tenantId",
 *         in="query",
 *         required=true,
 *         description="Id del tenant del que queremos sacar la informacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Devuelve info de una cloud de prisma.",
 *     @OA\Response(response="200", description="Devuelve todas las cloud de PrismaCloud de un tenant.")
 * )
 */
$app->get('/api/getPrismaSusInfo', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$parametros = $request->getQueryParams();
	$cloud = $prisma->getAccountInfo($parametros["tenantId"], $parametros["cloud"]);
	$responseData["cloud"] = json_decode($cloud);
	$db = new Revision(DB_SERV);
	$activos = $db->getActivoBySusId($parametros["tenantId"]);

	if (isset($activos)) {
		$responseData["activos"] = $activos;
	} else {
		$responseData["activos"] = null;
	}

	$response->getBody()->write(json_encode($responseData));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/getPrismaAlertInfo",
 *	  tags={"PrismaCloud"},
 *    @OA\Parameter(
 * 	   name="alertId",
 * 	   in="query",
 * 	   required=true,
 * 	   description="Id de la alerta de la que queremos sacar la informacion.",
 * 	   @OA\Schema(type="string")
 *   ),
 *  summary="Devuelve info de una alerta de prisma.",
 * @OA\Response(response="200", description="Devuelve info de una alerta de prisma.")
 * )
 */
$app->get('/api/getPrismaAlertInfo', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$parametros = $request->getQueryParams();
	$cloud = $prisma->getPrismaAlertInfo($parametros["alertId"]);
	$responseData["alert"] = json_decode($cloud);
	$response->getBody()->write(json_encode($responseData));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPrismaCloudFromTenant",
 *     tags={"PrismaCloud"},
 *	   @OA\Parameter(
 *         name="tenantId",
 *         in="query",
 *         required=true,
 *         description="Id del tenant del que queremos sacar la informacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Devuelve todas las cloud de PrismaCloud de un tenant.",
 *     @OA\Response(response="200", description="Devuelve todas las cloud de PrismaCloud de un tenant.")
 * )
 */
$app->get('/api/getPrismaCloudFromTenant', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$parametros = $request->getQueryParams();
	$cloud = $prisma->getPrismaCloudsFromTenant($parametros["tenantId"]);
	$cloud = json_decode($cloud, true);
	$clouds = array();
	$responseData[ERROR] = false;

	if (is_array($cloud) && !empty($cloud)) {
		$revision = new Revision(DB_SERV); // Instancia de la clase revision

		foreach ($cloud as $value) {
			$value["tenant"] = $cloud[0]["name"];

			// Verificar si la suscripción tiene activos
			$check = $revision->checkSuscriptionHasActivos($value["accountId"]);
			$value["asociacion"] = $check["asociacion"];
			$value["hasReview"] = $revision->existsReviewForSuscription($value["accountId"]);

			array_push($clouds, $value);
		}
		$responseData["cloud"] = $clouds;
		$response->getBody()->write(json_encode($responseData));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$responseData[ERROR] = true;
		$responseData[MESSAGE] = "No se encontraron datos de Prisma Cloud para el tenant proporcionado.";
		$response->getBody()->write(json_encode($responseData));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(404);
	}
});

/**
 * @OA\Get(
 *     path="/api/getPrismaAlertByReview",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve todas las alertas dado una review de Prisma.",
 *  *     @OA\Parameter(
 *         name="cloudName",
 *         in="query",
 *         required=true,
 *         description="Name del cloud.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve todas las alertas dada una review.")
 * )
 */
$app->get('/api/getPrismaAlertByReview', function (Request $request, Response $response) {
	try {
		$params = $request->getQueryParams();
		if (!isset($params["id"]) || empty($params["id"]) || !is_numeric($params["id"])) {
			throw new RouteException("ID de revisión no válido.");
		}
		$idReview = $params["id"];

		$db_revision = new Revision(DB_SERV);
		$idReview = $db_revision->obtainRevisionFromId($idReview);

		if (empty($idReview)) {
			throw new RouteException("No se encontró la revisión con el ID proporcionado.");
		}

		if (!isset($idReview[0]["cloudId"]) || empty($idReview[0]["cloudId"])) {
			throw new RouteException("No se encontró el ID de la nube asociado a la revisión.");
		}
		$cloudName = $idReview[0]["cloudId"];
		$prisma = new PrismaCloudAPI();
		$alertsOpen = $prisma->getPrismaAlertsByCloud($cloudName, 'cloud.accountId');
		$alertsClose = $prisma->getPrismaAlertsByCloud($cloudName, 'cloud.accountId', "dismissed");
		unset($prisma);
		$returnAlerts["alerts"]["open"] = $alertsOpen;
		$returnAlerts["alerts"]["close"] = $alertsClose;
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Error al obtener las alertas: " . $e->getMessage();
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}
	$response->getBody()->write(json_encode($returnAlerts));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/getPrismaAlertByCloud",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve todas las alertas dado una cloud de Prisma.",
 *         @OA\Parameter(
 *         name="cloudName",
 *         in="query",
 *         required=true,
 *         description="Name del cloud.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve todas las alertas dada una cloud.")
 * )
 */
$app->get('/api/getPrismaAlertByCloud', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$cloudName = $request->getQueryParams()["cloudName"];
	$alertsOpen = json_decode($prisma->getPrismaPoliciesByCloud($cloudName, 'cloud.accountId'));
	$alertsDismissed = json_decode($prisma->getPrismaPoliciesByCloud($cloudName, 'cloud.accountId', "dismissed"));

	$resultado = [
		"openAlerts"      => $alertsOpen,
		"dismissedAlerts" => $alertsDismissed
	];

	$response->getBody()->write(json_encode($resultado));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPrismaAlertsByPolicy",
 *     tags={"PrismaCloud"},
 *     summary="Devuelve todas las alertas dado una policy de Prisma.",
 *         @OA\Parameter(
 *         name="policyId",
 *         in="query",
 *         required=true,
 *         description="Id de la policy.",
 *         @OA\Schema(type="string")
 *     ),
 *    @OA\Response(response="200", description="Devuelve todas las alertas dada una policy.")
 */
$app->get('/api/getPrismaAlertsByPolicy', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$policyId = $request->getQueryParams()["policyId"] ?? "";
	$cloudName = $request->getQueryParams()["cloudId"] ?? "";
	$status = $request->getQueryParams()["status"] ?? "open";

	$alertsJson = $prisma->getPrismaAlertsByPolicy($policyId, $cloudName, $status);

	$alerts = json_decode($alertsJson);

	$response->getBody()->write(json_encode($alerts));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/getInfoUser",
 *     tags={"Usuarios"},
 *     summary="Obtiene información propia del usuario basado en la sesión.",
 *     @OA\Response(response="200", description="Devuelve la información del usuario.")
 * )
 */
$app->get('/api/getInfoUser', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data[ERROR] = false;
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	if (isset($user[0])) {
		foreach ($user[0]["roles"] as $key => $value) {
			$role = $db->getRoleByName($value);
			$user[0]["roles"][$key] = $role;
		}
		$response_data["user"] = $user[0];
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha encontrado el usuario.";
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getTokensUser",
 *     tags={"Usuarios"},
 *     summary="Obtiene los tokens asociados al usuario.",
 *     @OA\Response(response="200", description="Devuelve los tokens del usuario.")
 * )
 */
$app->get('/api/getTokensUser', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data[ERROR] = false;
	$db = new Usuarios(DB_USER);
	$tokens = $db->getTokensUser($token['data']);
	if (isset($tokens[0])) {
		$response_data["tokens"] = $tokens;
	} else {
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "No se han encontrado tokens.";
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getProyectosNoCreados",
 *     tags={"Evaluación / PAC"},
 *     summary="Obtiene proyectos(PACs) que no estén creados en los sistemas de información.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Id del activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene proyectos(PACs) que no estén creados en los sistemas de información.")
 * )
 */
$app->get('/api/getProyectosNoCreados', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Activos(DB_SERV);
	$activo = $db->getActivo($parametros["id"]);
	if (isset($activo[0])) {
		$db = new Activos();
		$proyectos = $db->getProyectos();
		$db = new Activos(DB_SERV);
		$hijos = $db->getHijos($activo[0]["id"]);
		$sistemas = getActivoByTipo($hijos, 33, "tipo_id");
		$proyectosCreadosPorSistema = [];

		foreach ($sistemas as $sistema) {
			$proyectosCreados = $db->getSeguimientoByActivoId($sistema["id"]);
			$proyectosCreadosPorSistema[] = array_column($proyectosCreados, "proyecto_id");
		}

		$idsTodosProyectos = array_column($proyectos, 'id');
		$proyectosNoRepetidos = $idsTodosProyectos;

		foreach ($idsTodosProyectos as $proyectoId) {
			$estaEnTodosLosSistemas = true;
			foreach ($proyectosCreadosPorSistema as $proyectosCreados) {
				if (!in_array($proyectoId, $proyectosCreados)) {
					$estaEnTodosLosSistemas = false;
					break;
				}
			}
			if ($estaEnTodosLosSistemas) {
				$proyectosNoRepetidos = array_diff($proyectosNoRepetidos, [$proyectoId]);
			}
		}

		$proyectosNoRepetidos = array_filter($proyectos, function ($proyecto) use ($proyectosNoRepetidos) {
			return in_array($proyecto['id'], $proyectosNoRepetidos);
		});

		sort($proyectosNoRepetidos);
		$response_data["proyectos"] = $proyectosNoRepetidos;
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se han encontrado sistemas en este servicio.";
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/getPacEvalServicio",
 *   tags={"Evaluación / PAC"},
 *   summary="Obtiene el PAC de un servicio.",
 * *   @OA\Parameter(
 * 	   name="id",
 * 	   in="query",
 * 	   required=true,
 * 	   description="Id del activo.",
 * 	   @OA\Schema(type="string")
 * *   ),
 * *   @OA\Parameter(
 * 	   name="fecha",
 * 	   in="query",
 * 	   required=true,
 * 	   description="Fecha de la evaluación.",
 * * 	   @OA\Schema(type="string")
 * *   ),
 * *   @OA\Parameter(
 * 	   name="version",
 * 	   in="query",
 * 	   required=false,
 * 	   description="Versión de la evaluación.",
 * * 	   @OA\Schema(type="string")
 * *   ),
 * *   @OA\Response(response="200", description="Obtiene el PAC de un servicio.")
 */
$app->get('/api/getPacEvalServicio', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) && empty($parametros['id']) && !is_numeric($parametros['id']) && !isset($parametros[FECHA])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}

	if (!isset($parametros[FECHA]) && !isset($parametros["version"])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta la fecha o version en la solicitud.", 400);
	}
	$db = new Activos(DB_SERV);
	$activo = $db->getActivo($parametros['id']);
	if (!isset($activo[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no existe.", 400);
	}
	$activos = $db->getTree($activo[0]);
	$activos = array_merge($activos, $activo);
	$activos = getActivosUnicos($activos);

	if (isset($parametros["version"])) {
		$preguntas = $db->getPreguntasVersionByFecha($parametros["version"]);
	}
	if (isset($parametros[FECHA])) {
		$preguntas = $db->getPreguntasEvaluacionByFecha($parametros[FECHA]);
	}
	if (!isset($preguntas[0][PREGUNTAS])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "No se han encontrado preguntas para la version o fecha indicada.", 400);
	}
	$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
	$activosObligatorios = getActivosObligatoriosSinRepetir($activos);
	$activos = array_merge($activos, $activosObligatorios);
	$comparacion = $db->getCompararNormativa("all", $activos, $preguntas);
	$normativas = getNormativas($comparacion);
	$preguntas = getUsfByPreguntas($preguntas, $normativas);

	$count = 0;
	$usfeval = array();
	$usfpac = array();
	foreach ($activos as $activo) {
		$db = new Activos();
		$activos[$count][AMENAZAS] = $db->getAmenazasByActivoId($activo['tipo']);
		$activos[$count][AMENAZAS] = obtenerUsfAmenazas($activos[$count][AMENAZAS]);
		foreach ($activos[$count][AMENAZAS] as &$amenaza) {
			$usfct['Proactivo'] = 0;
			$usfct['Reactivo'] = 0;
			foreach ($amenaza['usf'] as $key => $usf) {
				$defUsf = $usf;
				$usf = $usf['usf_id'];
				$index = array_key_exists($usf, $usfeval);
				if (!$index) {
					$usfeval[$usf] = array(TOTAL => 0, 'ct' => 0, 'nc' => 0);
				}
				$total = array_column($preguntas, $usf);
				if (count($total) !== 0) {
					$usfeval[$usf][TOTAL] = count($total);
					$existe = array_search('1', $total);
					if ($existe !== false) {
						$usfeval[$usf]['ct'] = (int) array_count_values($total)['1'];
					} else {
						$usfeval[$usf]['ct'] = 0;
					}
					$usfeval[$usf]['nc'] = $usfeval[$usf][TOTAL] - $usfeval[$usf]['ct'];
					$usfeval[$usf]['ctm'] = $usfeval[$usf]['ct'] / $usfeval[$usf][TOTAL] * 100;
					if ($usfeval[$usf]['ctm'] >= 85) {
						$usfct[$defUsf['tipo']]++;
					}
					$amenaza['usf'][$usf] = $usfeval[$usf];
					if (isset($defUsf['id_proyecto'])) {
						$proyecto = $db->getProyectoById($defUsf['id_proyecto'])[0];
						$usfpac[$proyecto['cod']]['nombre'] = $proyecto['nombre'];
						$usfpac[$proyecto['cod']]['descripcion'] = $proyecto['descripcion'];
						$usfpac[$proyecto['cod']]['tareas'] = $proyecto['tareas'];
						$usfpac[$proyecto['cod']]['usf'][$usf] =  $amenaza['usf'][$usf];
						$amenaza['usf'][$usf]["proyecto"] = $proyecto['nombre'];
					}
				}
				unset($amenaza['usf'][$key]);
			}
		}

		$count++;
	}
	$response_data['pac'] = $usfpac;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getRiesgos",
 *     tags={"Evaluación / ERS"},
 *     summary="Obtiene los riesgos de las amenazas dado un sistema de información y una evaluación.",
 *     @OA\Parameter(
 *         name="sistemas",
 *         in="query",
 *         required=true,
 *         description="Obtiene los ID de los activos en un string separados por ','.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="serv",
 *         in="query",
 *         required=true,
 *         description="ID de un servicio.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene los riesgos de las amenazas dado un sistema de información y una evaluación.")
 * )
 */
$app->get('/api/getRiesgos', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['serv']) && empty($parametros['serv']) && !is_numeric($parametros['serv'])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del servicio en la solicitud o no es valido.", 400);
	}
	if (!isset($parametros['sistemas']) && !isset($parametros['sistemas'][0]) && !is_numeric($parametros['sistemas'][0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Falta el id del sistema en la solicitud o no es valido.", 400);
	}
	$db = new Activos(DB_SERV);
	$sistemas = explode(",", $parametros['sistemas']);
	$array_eval = [];
	$servId = $db->getActivo($parametros['serv']);
	if (!isset($servId[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no existe.", 400);
	}
	if ($servId[0]['tipo'] !== 42) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "El activo no es de tipo Servicio de Negocio.", 400);
	}
	$servId = $servId[0]['id'];
	$bia = $db->getBia($servId);
	if (!isset($bia[0])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "No se ha encontrado el BIA del servicio.", 400);
	}
	$resultadobia = calcularBia($bia);
	$array_eval[] = $resultadobia;
	foreach ($sistemas as $sistema) {
		$id = $db->getActivoIdByNombre($sistema)[0]['id'];
		$fecha = $db->getFechaEvaluaciones($id, true);
		$activos = getActivosParaEvaluacion($id);
		if (isset($fecha[0])) {
			if ($fecha[0]["tipo_tabla"] == "evaluaciones") {
				$preguntas = $db->getPreguntasEvaluacionByFecha($fecha[0]["id"]);
			} else {
				$preguntas = $db->getPreguntasVersionByFecha($fecha[0]["id"]);
			}
			if (isset($preguntas[0][PREGUNTAS])) {
				$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
			}
			$eval = getEvaluacionActivos($activos, $preguntas);
			$array_eval[] = $eval["activos"];
		}
	}
	$response->getBody()->write(json_encode($array_eval));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/getRiesgosServicio",
 *     tags={"Evaluación / ERS"},
 *     summary="Devuelve el riesgo de un servicio en una fecha especificada.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un servicio.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve el riesgo de un servicio en una fecha especificada.")
 * )
 */
$app->get('/api/getRiesgosServicio', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	if (!isset($parametros['id']) && empty($parametros['id']) && !is_numeric($parametros['id']) && !isset($parametros[FECHA])) {
		return ERRORRESPONDER->createErrorResponse($request->getUri()->getPath(), "Faltan parametros requeridos.", 400);
	}
	$db = new Activos(DB_SERV);
	$activos = getActivosParaEvaluacion($parametros["id"]);
	if ($parametros[FECHA] !== 'null') {
		if (isset($parametros["version"])) {
			$preguntas = $db->getPreguntasVersionByFecha($parametros["version"]);
		} else {
			$preguntas = $db->getPreguntasEvaluacionByFecha($parametros[FECHA]);
		}

		if (isset($preguntas[0][PREGUNTAS])) {
			$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
			$preguntas = prepararPreguntas($preguntas, $parametros["id"]);
		}
		$response_data = getEvaluacionActivos($activos, $preguntas);
		$response->getBody()->write(json_encode($response_data));
	}
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getMedia",
 *     tags={"Evaluación / ERS"},
 *     summary="Obtener la media de riesgo dado un activo.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtener la media de riesgo dado un activo.")
 * )
 */
$app->get('/api/getMedia', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();

	$id = $parametros["id"];
	$db = new Activos(DB_SERV);
	$hijos = $db->getHijos($id);
	$arrayRiesgo = array();
	$arrayRiesgo = [];
	foreach ($hijos as $hijo) {
		if ($hijo["tipo_id"] == 33) {
			$fecha = $db->getFechaEvaluaciones($hijo["id"], true);
			if (isset($fecha[0])) {
				if ($fecha[0]["tipo_tabla"] == "evaluaciones") {
					$preguntas = $db->getPreguntasEvaluacionByFecha($fecha[0]["id"]);
				} else {
					$preguntas = $db->getPreguntasVersionByFecha($fecha[0]["id"]);
				}
				$activos = getActivosParaEvaluacion($hijo["id"]);

				if (isset($preguntas[0])) {
					$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
					$preguntas = prepararPreguntas($preguntas, $hijo["id"]);
				}
				$eval = getEvaluacionActivos($activos, $preguntas);
				$bia = $db->getBia($id);
				if (isset($bia)) {
					$resultadobia = calcularBia($bia);
				}
				$amenazas = getcalculoamenazas($eval["amenazas"], $resultadobia);
				$arrayRiesgo[] = $amenazas["riesgoa"];
			} else {
				$amenazas["riesgoa"] = "No evaluado";
				$arrayRiesgo[] = $amenazas["riesgoa"];
			}
		}
	}
	$response_data['media'] = obtenerMedia($arrayRiesgo);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPlan",
 *     tags={"Planes / Servicios"},
 *     summary="Obtener el plan de servicios disponible.",
 *     @OA\Response(
 *         response=200,
 *         description="Obtener el plan de servicios disponible.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="user", type="string", description="Correo electrónico del usuario."),
 *             @OA\Property(property="plan", type="string", description="Detalles del plan de servicios."),
 *             @OA\Property(property="error", type="boolean", description="Estado de error de la solicitud.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="No autorizado. Token inválido o no presente."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor."
 *     )
 * )
 */
$app->get('/api/getPlan', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$plan = $db->getPlan();
	$response_data['user'] = $user[0]['email'];
	$response_data['plan'] = $plan;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/islogged",
 *     tags={"Autenticación", "Usuarios"},
 *     summary="Devuelve el email del usuario si está loggeado.",
 *     @OA\Response(response="200", description="Devuelve el email del usuario si está loggeado.")
 * )
 */
$app->get('/api/islogged', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	if (isset($user[0])) {
		$response_data['user'] = $user[0]['email'];
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getSistemasGeneranIngresos",
 *     tags={"Activos"},
 * 	   @OA\Parameter(
 *         name="start",
 *         in="query",
 *         required=true,
 *         description="Activo desde el que empieza la búsqueda.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="total",
 *         in="query",
 *         required=true,
 *         description="Total de activos que devuelve.",
 *         @OA\Schema(type="integer")
 *     ),
 *     summary="Devuelve todos los sistemas que generan ingresos para la compañía.",
 *     @OA\Response(response="200", description="Devuelve todos los sistemas que generan ingresos para la compañía.")
 * )
 */
$app->get('/api/getServiciosExternos', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$paginacion = setPaginacion($parametros);
	$db = new Activos(DB_SERV);
	$servicios = $db->getActivosByTipo(42, null, false, true);
	$response_data["total"] = count($servicios);
	$resultado = array();
	$servicios = array_slice($servicios, $paginacion["start"]);
	foreach ($servicios as $index => $servicio) {
		if ($index == $paginacion["total"]) {
			break;
		}
		$activo = [];
		$hijo3PS = hijosCon3PS($servicio, $db);
		$hijoConEval = hijosConEvalNorm($servicio, $db);
		if ($hijo3PS) {
			$activo["evaluacion3PS"] = true;
		} else {
			$activo["evaluacion3PS"] = false;
		}
		if ($hijoConEval) {
			$activo["evaluacionNorm"] = true;
		} else {
			$activo["evaluacionNorm"] = false;
		}
		$activo["servicio"] = $servicio["nombre"];
		$padres = $db->getFathersNew($servicio["id"]);
		getPadresFromArray($padres, $activo);
		if ($servicio["externo"] == "1") {
			$activo["servicioExpuesto"] = true;
		} else {
			$activo["servicioExpuesto"] = false;
		}
		$resultado[] = $activo;
	}
	$response_data["activos"] = $resultado;
	if ($index + 1 == sizeof($servicios)) {
		$index = $index + 1;
	}
	$response_data["sistemas_analizados"] = $index;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getTratamientoDeDatos",
 *     tags={"Activos"},
 * 	   @OA\Parameter(
 *         name="start",
 *         in="query",
 *         required=true,
 *         description="Activo desde el que empieza la búsqueda.",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="total",
 *         in="query",
 *         required=true,
 *         description="Total de activos que devuelve.",
 *         @OA\Schema(type="integer")
 *     ),
 *     summary="Devuelve todos los sistemas que manejan datos de caracter personal.",
 *     @OA\Response(response="200", description="Devuelve todos los sistemas que manejan datos de caracter personal.")
 * )
 */
$app->get('/api/getTratamientoDeDatos', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$pagination = getPagination($parametros);
	$start = $pagination["start"];
	$total = $pagination["total"];
	$db = new Activos(DB_SERV);
	$productos = $db->getActivosByTipo(67, null, false);
	$response_data["total"] = count($productos);
	$productos = array_slice($productos, $start);
	$maxPagination = false;
	foreach ($productos as $index => $producto) {
		$productos[$index]["biaConfig"] = false;
		$productos[$index]["activoConfig"] = false;
		if ($index == $total) {
			$maxPagination = true;
			break;
		}
		$servicios = $db->getHijosTipo($producto["id"], "Servicio de Negocio");
		foreach ($servicios as $servicio) {
			if (servicioTieneConf($servicio)) {
				$productos[$index]["biaConfig"] = true;
			}
			$sistemas = $db->getHijosTipo($servicio["id"], "Sistema de Información");
			foreach ($sistemas as $indexSistema => $sistema) {
				$hijos = $db->getHijosTipo($sistema["id"], "Tratamiento de Datos");
				if (sizeof($hijos) > 0) {
					$productos[$index]["activoConfig"] = true;
					$sistemas[$indexSistema]["activoConfig"] = true;
				} else {
					$sistemas[$indexSistema]["activoConfig"] = false;
				}
			}
			$productos[$index]["sistemas"][] = $sistemas;
			$productos[$index]["servicios"][] = $servicio["nombre"];
		}
	}
	if (!$maxPagination) {
		$index = $index + 1;
	}
	$response_data["productos"] = $productos;
	$response_data["productosAnalizados"] = $index;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getProductosContinuidad",
 *     tags={"Activos"},
 *     summary="Devuelve productos con continuidad y sin continuidad.",
 *     @OA\Response(response="200", description="Devuelve todos los sistemas que tienen evaluación de 3PS junto con la fecha y su servicio.")
 * )
 */
$app->get('/api/getProductosContinuidad', function (Request $request, Response $response) {
	$response_data[ERROR] = false;
	$token = $request->getAttribute(TOKEN);
	$db_user = new Usuarios(DB_USER);
	$user = $db_user->getUser($token['data']);
	$db_serv = new Activos(DB_SERV);
	$seguimientos = $db_serv->getSeguimientoByPacId(18);
	$additionalAccess = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
	$productos = $db_serv->getActivosByTipo(67, $user[0], false, false, $additionalAccess);
	$ignorados = 0;
	foreach ($seguimientos as $sistema) {
		if ($sistema["estado"] != "Finalizado") {
			continue;
		}
		$activo = $db_serv->getActivo($sistema["activo_id"]);
		$activo = $activo[0];
		if ($activo['archivado'] == 1) {
			continue;
		}
		$padres = $db_serv->getFathersNewByTipo($activo['id'], "Producto");
		if (!isset($padres[0])) {
			$ignorados++;
		}
		foreach ($padres as $padre) {
			$padre = $db_serv->getActivo($padre["id"]);
			if ($padre[0]['archivado'] == 1) {
				continue;
			}
			$padre[0]['Activo'] = $activo;
			$productosContinuidad[] = $padre[0];
		}
	}
	$total = 0;
	foreach ($productos as $index => $producto) {
		$productos[$index]['continuidad'] = false;
		foreach ($productosContinuidad as $productoContinuidad) {
			if ($producto['id'] == $productoContinuidad['id']) {
				$productos[$index]['continuidad'] = true;
				$total++;
				break;
			}
		}
	}
	$response_data["productos"] = $productos;
	// $activos = getSistemas3PS();
	$response->getBody()->write(json_encode($response_data)); // cambiart por response_data
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getSistemas3PS",
 *     tags={"Activos"},
 *     summary="Devuelve todos los sistemas que tienen evaluación de 3PS junto con la fecha y su servicio.",
 *     @OA\Response(response="200", description="Devuelve todos los sistemas que tienen evaluación de 3PS junto con la fecha y su servicio.")
 * )
 */
$app->get('/api/getSistemas3PS', function (Request $_, Response $response) {
	$response_data[ERROR] = false;
	$activos = getSistemas3PS();
	$response->getBody()->write(json_encode($activos)); // cambiart por response_data
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDashboardActivos",
 *     tags={"Activos"},
 *     summary="Devuelve los valores para la gráfica de tipos de activos.",
 *     @OA\Response(response="200", description="Devuelve los valores para la gráfica de tipos de activos.")
 * )
 */
$app->get('/api/getDashboardActivos', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$response_data['user'] = $user[0]['email'];
	$response_data['activostipo'] = obtenerNombreActivos($db->getCountActivos());
	$response_data['activoslist'] = $db->getAllActivos();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDashboardBia",
 *     tags={"Evaluación / EAE"},
 *     summary="Devuelve los valores para la gráfica de cumplimiento de bia.",
 *     @OA\Response(response="200", description="Devuelve los valores para la gráfica de cumplimiento de bia.")
 * )
 */
$app->get('/api/getDashboardBia', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	$response_data[ERROR] = false;
	$response_data['user'] = $user[0]['email'];
	$response_data['bia'] = obtenerServiciosBia();
	$response_data['bialist'] = obtenerServiciosBia(true);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDashboardEcr",
 *     tags={"Evaluación / ECR"},
 *     summary="Devuelve las fechas de evaluación de todos los sistemas junto a su servicio padre.",
 * 	   description="Devuelve un array con todos los sistemas con su fecha de evaluación y su servicio padre. Si tiene mas de un servicio padre, el sistema aparece repetido.",
 *     @OA\Response(response="200", description="Devuelve un array")
 * )
 */
$app->get('/api/getDashboardEcr', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$sistemas = $db->getActivosByTipo(33, null, false);
	$response_data = array("error" => false, "ecrlist" => array());
	if (empty($sistemas)) {
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}

	foreach ($sistemas as $sistema) {
		if ($sistema["archivado"] === 0) {
			$evaluaciones = $db->getFechaEvaluaciones($sistema["id"], true);
			if (isset($evaluaciones[0])) {
				$fecha = $evaluaciones[0]["fecha"];
			} else {
				$fecha = "No evaluado.";
			}
			$padres = $db->getFathers($sistema);
			if (isset($padres)) {
				foreach ($padres as $padre) {
					if ($padre["archivado"] === 0 && $padre["tipo"] == 42) {
						array_push($response_data["ecrlist"], array(
							"ID" => $sistema["id"],
							"Nombre_Sistema" => $sistema["nombre"],
							"Nombre_Servicio" => $padre["nombre"],
							"Fecha_Evaluacion" => $fecha
						));
					}
				}
			} else {
				array_push($response_data["ecrlist"], array(
					"ID" => $sistema["id"],
					"Nombre_Sistema" => $sistema["nombre"],
					"Nombre_Servicio" => "Sin servicio padre",
					"Fecha_Evaluacion" => $fecha
				));
			}
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDashboardErs",
 *     tags={"Evaluación / ERS"},
 *     summary="Devuelve los valores para la gráfica de ERS.",
 *     @OA\Response(response="200", description="Devuelve los valores para la gráfica de ERS.")
 * )
 */
$app->get('/api/getDashboardErs', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$sistemas = $db->getActivosByTipo(33, null, false);
	$resultado = array();
	foreach ($sistemas as $key => $sistema) {
		if ($key >= 15) {
			break;
		}
		$fecha = getLastfecha($db, $sistema["id"]);
		$direccion = getParentescobySistemaId(array($sistema), 124);
		$area = getParentescobySistemaId(array($sistema), 123);
		$padres = $db->getFathers($sistema);
		foreach ($padres as $padre) {
			if ($padre["tipo"] == 42) {
				if (!isset($padre)) {
					break;
				}
				$bia = $db->getBia($padre["id"]);
				if (isset($bia)) {
					$resultadobia = calcularBia($bia);
				}
				$activos = getActivosParaEvaluacion($sistema["id"]);
				if ($fecha["fecha"] != "No evaluado") {
					$preguntas = $db->getPreguntasVersionByFecha($fecha["id"]);
					if (!isset($preguntas[0])) {
						$preguntas = $db->getPreguntasEvaluacionByFecha($fecha["id"]);
					}
					if (isset($preguntas[0][PREGUNTAS])) {
						$preguntas = json_decode($preguntas[0][PREGUNTAS], true);
						$preguntas = prepararPreguntas($preguntas, $sistema["id"]);
					}
					$eval = getEvaluacionActivos($activos, $preguntas);
					$amenazas = getcalculoamenazas($eval["amenazas"], $resultadobia);
				} else {
					$amenazas["riesgoa"] = "No evaluado";
				}
				foreach ($padres as $padre) {
					if ($padre["tipo"] == 42) {
						array_push($resultado, array(
							"id" => $sistema["id"],
							"direccion" => $direccion["nombre"],
							"area" => $area["nombre"],
							"servicio" => $padre["nombre"],
							"sistema" => $sistema["nombre"],
							"fecha" => $fecha["fecha"],
							"riesgo" => $amenazas["riesgoa"]
						));
					}
				}
			}
		}
	}
	$response_data[ERROR] = false;
	$response_data["erslist"] = $resultado;

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getDashboardPac",
 *     tags={"Evaluación / PAC"},
 *     summary="Devuelve los valores para la gráfica de PAC.",
 *     @OA\Response(response="200", description="Devuelve los valores para la gráfica de PAC.")
 * )
 */
$app->get('/api/getDashboardPac', function (Request $_, Response $response) {
	$db = new Activos(DB_SERV);
	$response_data[ERROR] = false;
	$seguimientos = $db->getSeguimientoByActivoId();
	$sistemas = array_unique(array_column($seguimientos, 'activo_id'));
	$sistemasconservicios = getServiciobySistemaId($sistemas);
	$sistemasIds = array_column($sistemasconservicios, "id");
	foreach ($seguimientos as $key => $seguimiento) {
		$index = array_search($seguimiento["activo_id"], $sistemasIds);
		if ($index !== false) {
			$padres = $sistemasconservicios[$index]["padres"];
			$archivados = array_column($padres, "archivado");
			$seguimientos[$key]["servicio"] = $padres;
			if (array_sum($archivados) !== 0) {
				$seguimientos[$key]["archivado"] = 1;
			} else {
				$seguimientos[$key]["archivado"] = 0;
			}
		}
	}
	$seguimientosSinArchivar = array_filter($seguimientos, function ($seguimiento) {
		return isset($seguimiento["archivado"]) && $seguimiento["archivado"] === 0;
	});
	$pacs = array_count_values(array_column($seguimientosSinArchivar, 'nombrepac'));
	$pacsreturn = array();
	foreach ($pacs as $pac => $num) {
		array_push($pacsreturn, array($pac, $num));
	}
	$estados = array_count_values(array_column($seguimientosSinArchivar, 'estado'));
	$estadosreturn = array();
	foreach ($estados as $estado => $numero) {
		array_push($estadosreturn, array($estado, $numero));
	}
	$response_data['seguimientos'] = $seguimientos;
	$response_data['estados'] = $estadosreturn;
	$response_data['proyectoslist'] = $pacsreturn;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 * 		path="/api/getDashboardGBU",
 * 		tags={"Activos"},
 * 		summary="Devuelve los valores para la gráfica de GBU.",
 * 		@OA\Response(response="200", description="Devuelve los valores para la gráfica de GBU.")
 * )
 */
$app->get('/api/getDashboardGBU', function (Request $_, Response $response) {
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$servicio["id"] = 27384;
	$response_data["gbu"] = $db->getHijos($servicio["id"]);
	$response->getBody()->write(json_encode($response_data["gbu"]));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getActivosTipo",
 *     tags={"Activos"},
 *     summary="Devuelve todos los activos de un tipo.",
 *     @OA\Response(response="200", description="Devuelve todos los activos de un tipo.")
 * )
 */
$app->get('/api/getActivosTipo', function (Request $request, Response $response) {
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = MENSAJE_ACTIVOS_OBTENIDOS;
	$db = new Activos(DB_SERV);
	$parametros = $request->getQueryParams();
	if (!isset($parametros["tipo"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha especificado el tipo de activo.";
	} else {
		$activos = $db->getActivosByTipo($parametros["tipo"], null, false);
	}
	$response_data["activos"] = $activos;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/logout",
 *     tags={"Autenticación"},
 *     summary="Cierra la sesión de la aplicación.",
 *     @OA\Response(response="200", description="Cierra la sesión de la aplicación.")
 * )
 */
$app->get('/api/logout', function (Request $_, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Se ha cerrado la sesión correctamente.";
	if (isset($_SERVER['HTTP_X_ORIGINAL_HOST'])) {
		$domain = rawurlencode($_SERVER['HTTP_X_ORIGINAL_HOST']);
	} elseif (isset($_SERVER['SERVER_NAME'])) {
		$domain = rawurlencode($_SERVER['SERVER_NAME']);
	}
	$options = [
		"expires" => time() + 3600,
		'samesite' => 'Strict',
		'path' => '/',
		'domain' => $domain,
		'secure' => true,
		'httponly' => true,
	];
	setcookie("sst", "", $options);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getActivos",
 *     tags={"Activos"},
 *     summary="Devuelve los activos según el tipo y filtro especificado.",
 *     @OA\Parameter(
 *         name="archivado",
 *         in="query",
 *         required=false,
 *         description="Indica si está archivado o no.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="tipo",
 *         in="query",
 *         required=true,
 *         description="Indica si está archivado o no.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="archivado",
 *         in="query",
 *         required=true,
 *         description="Filtro por el que buscamos los activos, puede ser 'Todos', 'NoAct' y 'NoEcr'.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve los activos según el tipo y filtro especificado.")
 * )
 */
$app->get('/api/getActivos', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	if ($token) {
		$parametros = $request->getQueryParams();
		$db = new Usuarios(DB_USER);
		$user = $db->getUser($token['data']);
		$db = new Activos(DB_SERV);
		if (isset($parametros["archivado"])) {
			$archivado = $parametros["archivado"];
		} else {
			$archivado = 0;
		}
		$additionalAccess = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
		if ($parametros["tipo"] == "42a") {
			$servicios = $db->getActivosByTipo(42, $user[0], $archivado, false, $additionalAccess);
			$servicios = array_merge($servicios, $db->getActivosByTipo(67, $user[0], $archivado, false, $additionalAccess));
		} else {
			$servicios = $db->getActivosByTipo($parametros['tipo'], $user[0], $archivado, false, $additionalAccess);
		}


		if ($parametros["tipo"] == 42) {
			$serviciospermisos = $db->getActivosPermisos($user[0]['id']);
		} else {
			$serviciospermisos = array();
		}
		$servicios = array_merge($servicios, $serviciospermisos);

		if ($parametros["tipo"] == 42 && !empty($servicios)) {
			$sinbia = $db->getServiciosSinBia();
			$servicios = getMediaSistemasECR($servicios);
			$servidsinbia = array_column($sinbia, 'id');
			$biadesactualizado = $db->getServiciosBiaDesactualizado();
			$biadesactualizado = array_column($biadesactualizado, 'id');
			foreach ($servicios as $key => $serv) {
				$id = $serv["id"];
				$servicios[$key]['biaoutdated'] = in_array($id, $biadesactualizado);
				$servicios[$key]['bia'] = !in_array($id, $servidsinbia);
			}
			if ($parametros["filtro"] != "Todos") {
				if ($parametros["filtro"] == "NoAct") {
					$serviciosCompleto = $servicios;
					unset($servicios);
					$servicios = array();
					foreach ($serviciosCompleto as $key => $serv) {
						if ($serv['biaoutdated'] || !$serv['bia']) {
							array_push($servicios, $serv);
						}
					}
				}
				if ($parametros["filtro"] == "NoECR") {
					$serviciosCompleto = $servicios;
					unset($servicios);
					$servicios = array();
					foreach ($serviciosCompleto as $key => $serv) {
						if (!isset($serv['total']) || !isset($serv['ecr']) || ($serv['total'] != $serv["ecr"])) {
							array_push($servicios, $serv);
						}
					}
				}
			}
		}
		$response_data[TOTAL] = count($servicios);
		$response_data['servicios'] = $servicios;
		$response_data['user'] = $user[0]['email'];
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getChild/{id}",
 *     tags={"Activos"},
 *     summary="Devuelve el hijo de un activo.",
 *     @OA\Response(response="200", description="Devuelve el hijo de un activo.")
 * )
 */
$app->get('/api/getChild/{id}', function (Request $request, Response $response, array $args) {
	$token = $request->getAttribute(TOKEN);

	$id = $args['id'] ?? '';
	if (!is_numeric($id)) {
		$response_data = [
			ERROR => true,
			MESSAGE => "ID must be a number"
		];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}

	$db = new Activos(DB_SERV);
	$child = $db->getChild($id);
	$activo = $db->getActivo($id, $token['data']);

	$responseData = [
		ERROR => false,
		'padre' => $activo,
		'child' => $child,
	];

	$response->getBody()->write(json_encode($responseData));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getTree/{id}",
 *     tags={"Activos"},
 *     summary="Devuelve el árbol de hijos de un activo pasado como argumento.",
 *     @OA\Response(response="200", description="Devuelve el árbol de hijos de un activo pasado como argumento.")
 * )
 */
$app->get('/api/getTree/{id}', function (Request $request, Response $response, array $args) {
	$token = $request->getAttribute(TOKEN);
	$id = $args['id'];
	global $error;
	if (isset($id)) {
		$db = new Activos(DB_SERV);
		$servicio = $db->getActivo($id, $token['data'])[0];
		$respuesta = $db->getHijos($servicio["id"]);
		$servicio['tipo'] = $db->getClaseActivoById($servicio['tipo'])[0]['nombre'];
		array_unshift($respuesta, $servicio);

		$response_data[ERROR] = false;
		$response_data['tree'] = $respuesta;
	} else {
		$code = 204;
		$response_data[ERROR] = $error->getErrorForCode($code);
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $error->getMessageForCode($code);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/downloadTree",
 *     tags={"Activos"},
 *     summary="Genera un archivo de Xlsx con todos los hijos de un activo.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera un archivo de Xlsx con todos los hijos de un activo.")
 * )
 */
$app->get('/api/downloadTree', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$token = $request->getAttribute(TOKEN);
	global $error;
	if (isset($parametros["id"])) {
		$db = new Activos(DB_SERV);
		$servicio = $db->getActivo($parametros["id"], $token['data'])[0];
		$respuesta = $db->getTree($servicio, 'nombre');
		$servicio['tipo'] = $db->getClaseActivoById($servicio['tipo'])[0]['nombre'];
		$cabeceras = array("NOMBRE", "TIPO", "ID", "PADRE", "ARCHIVADO", "EXPUESTO");
		array_unshift($respuesta, $cabeceras, array('nombre' => $servicio['nombre'], 'tipo' => $servicio['tipo'], 'id' => $servicio['id'], 'padre' => '', 'archivado' => $servicio['archivado'], 'expuesto' => ''));
		$response_data[ERROR] = false;
		$response_data['tree'] = $respuesta;
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->fromArray($respuesta);
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="myfile.xlsx"');
		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		return $response->withStatus(200);
	} else {
		$code = 204;
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $error->getMessageForCode($code);
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Get(
 *     path="/api/obtainActivosPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Obtiene los activos de un pentest.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Id del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve los activos del pentest.")
 * )
 */
$app->get('/api/obtainActivosPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest(DB_SERV);
	$activos = $db->obtenerActivosPentest($parametros["id"]);
	$response->getBody()->write(json_encode($activos));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Get(
 *     path="/api/obtainActivosRevision",
 *     tags={"Evaluación / EAS"},
 *     summary="Obtiene los activos de una revisión.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="Id de la revisión.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve los activos de la revisión.")
 * )
 */
$app->get('/api/obtainActivosRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Revision(DB_SERV);
	$activos = $db->obtenerActivosRevision($parametros["id"]);
	$response->getBody()->write(json_encode($activos));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainPentestIssue",
 *     tags={"Evaluación / EVS"},
 *     summary="Obtiene las issues de un pentest.",
 *     @OA\Parameter(
 *         name="key",
 *         in="query",
 *         required=true,
 *         description="Key la issue a buscar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene las issues de un pentest.")
 * )
 */
$app->get('/api/obtainPentestIssue', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest("octopus_serv");
	$nombrePentest = $db->obtenerPentestIssue($parametros["key"]);
	if (isset($nombrePentest[0])) {
		$response->getBody()->write(json_encode($nombrePentest[0]["nombre"]));
	} else {
		$response->getBody()->write(json_encode("Sin pentest"));
	}
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/eliminarRevision",
 *     tags={"Evaluación / EVS"},
 *     summary="Elimina una revision.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la revision.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina una revision.")
 * )
 */
$app->get('/api/eliminarRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Revision("octopus_serv");
	$revision = $db->eliminarRevision($parametros["id"]);
	$response->getBody()->write(json_encode($revision));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/eliminarPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Elimina un pentest.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina un pentest.")
 * )
 */
$app->get('/api/eliminarPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest("octopus_serv");
	$pentests = $db->eliminarPentests($parametros["id"]);
	$response->getBody()->write(json_encode($pentests));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/cerrarRevision",
 *     tags={"Evaluación / EAS"},
 *     summary="Cierra una revision mandandola al de evaluaciones.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Cierra un pentest.")
 * )
 */
$app->get('/api/cerrarRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Revision cerrada correctamente";
	$db = new Revision("octopus_serv");
	$db->cambiarStatusRevision($parametros["id"], "2");
	$response->getBody()->write(json_encode("Pentest Cerrado"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/cerrarPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Cierra un pentest mandandolo listo el gestor de evaluaciones.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de un pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Cierra un pentest.")
 * )
 */
$app->get('/api/cerrarPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data[ERROR] = false;
	$db = new Pentest("octopus_serv");
	$db->cambiarStatusPentest($parametros["id"], "2");
	$response->getBody()->write(json_encode("Pentest Cerrado"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerSDLC",
 *     tags={"Evaluación / EVS / SDLC"},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la App de SDLC.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Obtiene la información de todas las aplicaciones de SDLC o de solo una si se ha especificado un ID.",
 *     @OA\Response(response="200", description="Obtiene la información de todas las aplicaciones de SDLC o de solo una si se ha especificado un ID.")
 * )
 */
$app->get('/api/obtenerSDLC', function (Request $request, Response $response) {
	$db = new Pentest("octopus_serv");
	$db_activos = new Activos("octopus_serv");
	$parametros = $request->getQueryParams();
	$aplicaciones = $db->obtenerAplicacionesSDLC($parametros);

	$kiuwanData = $db->getKiuwanData();

	foreach ($aplicaciones as $index => $aplicacion) {
		if (!empty($aplicacion["direccion_id"])) {
			$activo = $db_activos->getActivo($aplicacion["direccion_id"]);
			$aplicaciones[$index]["direccion"] = $activo[0]["nombre"];
		}

		if (!empty($aplicacion["area_id"])) {
			$activo = $db_activos->getActivo($aplicacion["area_id"]);
			$aplicaciones[$index]["area"] = $activo[0]["nombre"];
		}

		if (!empty($aplicacion["producto_id"])) {
			$activo = $db_activos->getActivo($aplicacion["producto_id"]);
			$aplicaciones[$index]["producto"] = $activo[0]["nombre"];
			$aplicaciones[$index]["criticidad"] = $activo[0]["critico"];
			$aplicaciones[$index]["exposicion"] = $activo[0]["expuesto"];
		}

		if (!empty($aplicacion["kiuwan_id"])) {
			$kiuwanSlot = array_filter($kiuwanData, function ($slot) use ($aplicacion) {
				return $slot["id"] == $aplicacion["kiuwan_id"];
			});

			if (!empty($kiuwanSlot)) {
				$kiuwanSlot = array_values($kiuwanSlot)[0];
				$aplicaciones[$index]["kiuwan_slot"] = $kiuwanSlot["app_name"];
				$aplicaciones[$index]["cumple_kpm"] = $kiuwanSlot["cumple_kpm"];
			} else {
				$aplicaciones[$index]["kiuwan_slot"] = "N/A";
				$aplicaciones[$index]["cumple_kpm"] = 0;
			}
		}

		$aplicaciones[$index]["fecha_analisis_kiuwan"] = $aplicacion["fecha_analisis_kiuwan"];

		if (isset($parametros["id"]) && $aplicacion["id"] == $parametros["id"]) {
			$response->getBody()->write(json_encode($aplicaciones[$index]));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(200);
		}
	}

	if (isset($parametros['app']) && !empty($parametros['app'])) {
		$aplicaciones = array_filter($aplicaciones, function ($aplicacion) use ($parametros) {
			return $aplicacion['app'] === $parametros['app'];
		});
	}

	$response->getBody()->write(json_encode(array_values($aplicaciones)));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerActivosPentest",
 *     tags={"Evaluación / EVS"},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Devuelve todos los activos de un pentest especificado.",
 *     @OA\Response(response="200", description="Devuelve todos los activos de un pentest especificado.")
 * )
 */
$app->get('/api/obtenerActivosPentest', function (Request $request, Response $response) {
	$db_activos = new Activos("octopus_serv");
	$db = new Pentest("octopus_serv");
	$parametros = $request->getQueryParams();
	$activos = $db->obtenerActivosPentest($parametros["id_pentest"]);
	foreach ($activos as $index => $activo) {
		$activos[$index] = $db_activos->getActivo($activo["id_activo"])[0];
	}
	$response->getBody()->write(json_encode($activos));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});
/**
 * @OA\Get(
 *     path="/api/obtenerActivosRevision",
 *     tags={"Evaluación / EAS"},
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la revisión.",
 *         @OA\Schema(type="string")
 *     ),
 *     summary="Devuelve todos los activos de una revisión especificada.",
 *     @OA\Response(response="200", description="Devuelve todos los activos de una revisión especificada.")
 * )
 */
$app->get('/api/obtenerActivosRevision', function (Request $request, Response $response) {
	$db_activos = new Activos("octopus_serv");
	$db = new Revision("octopus_serv");
	$parametros = $request->getQueryParams();
	$activos = $db->obtenerActivosRevision($parametros["id_revision"]);
	foreach ($activos as $index => $activo) {
		$activos[$index] = $db_activos->getActivo($activo["id_activo"])[0];
	}
	$response->getBody()->write(json_encode($activos));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerIssuesPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Devuelve todas las issues de un pentest especificado.",
 *     @OA\Response(response="200", description="Devuelve todas las issues de un pentest especificado.")
 * )
 */
$app->get('/api/obtenerIssuesPentest', function (Request $_, Response $response) {
	$db = new Pentest("octopus_serv");
	$issues = $db->getAllIssues();
	foreach ($issues as $index => $issue) {
		$issues[$index]["Pentest"] = $db->getPentestsIssues($issue["id_issue"])[0];
	}
	$response->getBody()->write(json_encode($issues));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getAlertasRevisionByID",
 *     tags={"Evaluación / EVS"},
 *     summary="Da información específica de un pentest según su ID.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la revision.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Da las alertas de una review.")
 * )
 */
$app->get('/api/getAlertasRevisionByID', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();

	try {
		if (!isset($parametros["id"]) || empty($parametros["id"]) || !is_numeric($parametros["id"])) {
			throw new RouteException("ID de la revisión no especificado o inválido.");
		}

		$revisionId = $parametros["id"];

		$db_revision = new Revision("octopus_serv");
		$alerts = $db_revision->obtenerVulnsRevision($revisionId);

		$revisionData = $db_revision->obtainRevisionFromId($revisionId);
		if (empty($revisionData) || !isset($revisionData[0]['cloudId'])) {
			throw new RouteException("No se encontró la suscripción asociada a la revisión.");
		}
		$suscriptionId = $revisionData[0]['cloudId'];
		$suscriptionName = $db_revision->getSuscriptionNameBySusId($suscriptionId);
		if (empty($suscriptionName) || !isset($suscriptionName[0]['suscription_name'])) {
			throw new RouteException("No se encontró el nombre de la suscripción.");
		}
		$suscription = $suscriptionName[0]['suscription_name'];

		if (!empty($alerts)) {
			$prisma = new PrismaCloudAPI();
			$alertsJson = $prisma->getPrismaAlertInfoV2($alerts);
			$decodedAlerts = json_decode($alertsJson);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new RouteException("Error al decodificar información de alertas: " . json_last_error_msg());
			}
			$alerts = $decodedAlerts;
		} else {
			$alerts = ["totalRows" => 0, "items" => []];
		}
	} catch (Exception $e) {
		$response_data = [
			ERROR => true,
			MESSAGE => "Error al obtener las alertas: " . $e->getMessage()
		];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	$response->getBody()->write(json_encode([
		"alerts" => $alerts,
		"suscription" => [
			"id" => $suscriptionId,
			"name" => $suscription
		]
	]));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/checkAlertStatusByIssueId",
 *     tags={"Evaluación / EVS"},
 *     summary="Comprueba el estado de las alertas asociadas a un issue.",
 *     @OA\Parameter(
 *         name="id_issue",
 *         in="query",
 *         required=true,
 *         description="ID del issue.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve el estado de las alertas asociadas al issue.")
 * )
 */
$app->get('/api/checkAlertStatusByIssueId', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();

	try {
		if (!isset($parametros['id_issue']) || empty($parametros['id_issue'])) {
			throw new RouteException('ID de issue no especificado o inválido.');
		}

		$issueKey = $parametros['id_issue'];

		$db_revision = new Revision('octopus_serv');
		$result = $db_revision->getAlertsByIssueKey($issueKey);
		$alertsIds = $result['alertIds'];

		if (!empty($alertsIds)) {
			$prisma = new PrismaCloudAPI();
			$alertsJson = $prisma->getPrismaAlertInfoV2($alertsIds, true);
			$decodedAlerts = json_decode($alertsJson, true);
			if (json_last_error() !== JSON_ERROR_NONE) {
				throw new RouteException('Error al decodificar información de alertas: ' . json_last_error_msg());
			}
			$alerts = $decodedAlerts;
		} else {
			$alerts = ['totalRows' => 0, 'items' => []];
		}
	} catch (Exception $e) {
		$response_data = [
			ERROR   => true,
			MESSAGE => 'Error al obtener las alertas: ' . $e->getMessage()
		];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	$response->getBody()->write(json_encode($alerts));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getInfoRevisionByID",
 *     tags={"Evaluación / EVS"},
 *     summary="Da información específica de un pentest según su ID.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la revision.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Da información específica de un pentest según su ID.")
 * )
 */
$app->get('/api/getInfoRevisionByID', function (Request $request, Response $response) {
	// Inicializamos la respuesta
	$response_data = [];
	$response_data[ERROR] = false;

	// Obtenemos los parámetros de la solicitud
	$parametros = $request->getQueryParams();

	// Creamos la conexión a la base de datos para "revision" y "usuarios"
	$db = new Revision("octopus_serv");
	$db_activos = new Activos("octopus_serv");
	$db_usuarios = new Usuarios(DB_USER);

	$revisionData = $db->obtainRevisionFromId($parametros["id"]);
	if (!empty($revisionData)) {
		if ($revisionData[0]["status"] == 1) {
			$revisionData[0]["status"] = "Abierta";
		} elseif ($revisionData[0]["status"] == 2) {
			$revisionData[0]["status"] = "Cerrada";
		}
		$user_id = $revisionData[0]['user_id'];

		if ($user_id) {
			$usuario = $db_usuarios->getUser($user_id);

			if (isset($usuario[0]['email'])) {
				$user_email = $usuario[0]["email"];
			} else {
				$user_email = "Usuario sin identificar. UserId: " . $user_id;
			}
		} else {
			$user_email = "Usuario no asignado a la revisión.";
		}

		$activosPentest = $db->obtenerActivosRevision($parametros["id"]);
		$issuesPentest = $db->obtenerVulnsRevision($parametros["id"]);

		// Preparar los datos a devolver
		$data = [];
		$data["revision"] = $revisionData[0];
		$data["activos"] = [];
		$data["vulns"] = [];

		// Procesar los activos
		foreach ($activosPentest as $activoPentest) {
			$activo = $db_activos->getActivo($activoPentest["id_activo"]);
			array_push($data["activos"], $activo[0]["nombre"]);
		}

		// Procesar las vulnerabilidades
		foreach ($issuesPentest as $issuePentest) {
			array_push($data["vulns"], $issuePentest["id_alert"]);
		}

		// Añadir el email del usuario a la respuesta
		$data["usuario"] = $user_email;

		// Escribir la respuesta con los datos obtenidos
		$response->getBody()->write(json_encode($data));
	} else {
		// Si no se encontró la revisión, devolver un error
		$response_data = [
			'status' => false,
			'message' => 'Revisión no encontrada.'
		];

		$response->getBody()->write(json_encode($response_data));
	}

	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *		path="/api/getOsaByType",
 *		tags={"Evaluación / EAS"},
 *		summary="Obtiene los OSAs.",
 *		@OA\Parameter(
 *			name="type",
 *			in="query",
 *			description="Tipo de Osas a obtener.",
 *			required=true,
 * 	      @OA\Schema(type="string")
 *		),
 *		@OA\Response(response="200", description="Obtiene los OSAs.")
 */
$app->get('/api/getOsaByType', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data = [];
	$response_data[ERROR] = false;
	if (!isset($parametros["type"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha especificado el tipo de OSAs a obtener.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
	$db = new DbOperations();
	$osa = $db->getOsaByType($parametros["type"]);
	$response_data["OSA"] = $osa;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *		path="/api/getOsaEvalByRevision",
 *		tags={"Evaluación / EAS"},
 *		summary="Obtiene los OSAs de una revisión.",
 *		@OA\Parameter(
 *			name="id",
 *			in="query",
 *			description="ID de la revisión.",
 *			required=true,
 * 	      @OA\Schema(type="string")
 *		),
 *		@OA\Response(response="200", description="Obtiene los OSAs de una revisión.")
 */
$app->get('/api/getOsaEvalByRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data = [];
	$response_data[ERROR] = false;
	if (!isset($parametros["id"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha especificado el ID de la revisión.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
	$db = new DbOperations(DB_SERV);
	$osa = $db->getOsaEvalByRevision($parametros["id"]);
	if (isset($osa[0])) {
		$osa = $osa[0];
	}
	$response_data["OSA"] = $osa;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/exportarPentestsExcel",
 *     tags={"Pentest"},
 *     summary="Exporta los pentest a un Excel con formato personalizado.",
 *     @OA\Response(response="200", description="Descarga un Excel con los pentest.")
 * )
 */
$app->get('/exportarPentestsExcel', function (Request $_request, Response $_response) {
	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();

	$sheet->setCellValue('A1', 'Producto');
	$sheet->setCellValue('B1', 'SRV');
	$sheet->setCellValue('C1', 'Solicitante');
	$sheet->setCellValue('D1', 'Persona de soporte');
	$sheet->setCellValue('E1', 'Responsable de proyecto');

	$sheet->getStyle('A1:E1')->getFont()->setBold(true);

	$db = new Pentest("octopus_serv");
	$db_users = new Usuarios(DB_USER);
	$db_activos = new Activos("octopus_serv");
	$pentests = $db->getPentests();


	$row = 2;
	foreach ($pentests as $pentest) {
		$solicitud_id = $pentest['solicitud_id'] ?? null;
		$user_email = '';
		if ($solicitud_id) {
			$user_id = $db->getUserBySolID($pentest['id']);
			if ($user_id) {
				$user = $db_users->getUser($user_id);
				$user_email = $user[0]['email'] ?? '';
			}
		}
		$persona_soporte = $pentest['mail_soporte'] ?? '';
		$resp_proyecto = $pentest['resp_proyecto'] ?? '';
		$activosPentest = $db->obtenerActivosPentest($pentest['id']);
		foreach ($activosPentest as $activoPentest) {
			$activos = $db_activos->getActivos([$activoPentest["id_activo"]]);
			foreach ($activos as $activo) {
				$tipo = $activo['tipo'];
				if ($tipo == 33) {
					$srvArr = $db_activos->getFathersNewByTipo($activo['id'], "Servicio de Negocio");
					foreach ($srvArr as $srvActivo) {
						$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
						$productoArr = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
						foreach ($productoArr as $productoActivo) {
							$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $user_email);
							$sheet->setCellValue("D$row", $persona_soporte);
							$sheet->setCellValue("E$row", $resp_proyecto);
							$row++;
						}
					}
				} elseif ($tipo == 67) {
					$productoArr = $db_activos->getActivos([$activo['id']]);
					foreach ($productoArr as $productoActivo) {
						$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
						$srvArr = $db_activos->getHijosTipo($activo['id'], "Servicio de Negocio");
						foreach ($srvArr as $srvActivo) {
							$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $user_email);
							$sheet->setCellValue("D$row", $persona_soporte);
							$sheet->setCellValue("E$row", $resp_proyecto);
							$row++;
						}
					}
				} elseif ($tipo == 42) {
					$srvArr = $db_activos->getActivos([$activo['id']]);
					foreach ($srvArr as $srvActivo) {
						$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
						$productoArr = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
						foreach ($productoArr as $productoActivo) {
							$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $user_email);
							$sheet->setCellValue("D$row", $persona_soporte);
							$sheet->setCellValue("E$row", $resp_proyecto);
							$row++;
						}
					}
				}
			}
		}
	}

	$sheet->getColumnDimension('A')->setWidth(40);
	$sheet->getColumnDimension('B')->setWidth(45);
	$sheet->getColumnDimension('C')->setWidth(45);
	$sheet->getColumnDimension('D')->setWidth(45);

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header("Content-Disposition: attachment;filename=Pentests.xlsx");
	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
	exit;
});

/**
 * @OA\Get(
 *     path="/exportarRevisionesExcel",
 *     tags={"Revisiones"},
 *     summary="Exporta las revisiones a un Excel con formato personalizado.",
 *     @OA\Response(response="200", description="Descarga un Excel con las revisiones.")
 * )
 */
$app->get('/exportarRevisionesExcel', function (Request $_request, Response $_response) {
	$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();

	$sheet->setCellValue('A1', 'Producto');
	$sheet->setCellValue('B1', 'SRV');
	$sheet->setCellValue('C1', 'Project Manager');

	$sheet->getStyle('A1:C1')->getFont()->setBold(true);

	$db = new Revision("octopus_serv");
	$db_activos = new Activos("octopus_serv");
	$revisiones = $db->getRevisiones();

	$row = 2;
	foreach ($revisiones as $revision) {
		$project_manager = $revision['resp_proyecto'] ?? '';
		$activosRevision = $db->obtenerActivosRevision($revision['id']);
		foreach ($activosRevision as $activoRevision) {
			$activos = $db_activos->getActivos([$activoRevision["id_activo"]]);
			foreach ($activos as $activo) {
				$tipo = $activo['tipo'];
				if ($tipo == 42) {
					$srvArr = $db_activos->getActivos([$activo['id']]);
					foreach ($srvArr as $srvActivo) {
						$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
						$productoArr = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
						foreach ($productoArr as $productoActivo) {
							$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $project_manager);
							$row++;
						}
					}
				} elseif ($tipo == 67) {
					$productoArr = $db_activos->getActivos([$activo['id']]);
					foreach ($productoArr as $productoActivo) {
						$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
						$srvArr = $db_activos->getHijosTipo($activo['id'], "Servicio de Negocio");
						foreach ($srvArr as $srvActivo) {
							$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $project_manager);
							$row++;
						}
					}
				} elseif ($tipo == 33) {
					$srvArr = $db_activos->getFathersNewByTipo($activo['id'], "Servicio de Negocio");
					foreach ($srvArr as $srvActivo) {
						$srv = isset($srvActivo['nombre']) ? $srvActivo['nombre'] : '';
						$productoArr = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
						foreach ($productoArr as $productoActivo) {
							$producto = isset($productoActivo['nombre']) ? $productoActivo['nombre'] : '';
							$sheet->setCellValue("A$row", $producto);
							$sheet->setCellValue("B$row", $srv);
							$sheet->setCellValue("C$row", $project_manager);
							$row++;
						}
					}
				}
			}
		}
	}

	$sheet->getColumnDimension('A')->setWidth(40);
	$sheet->getColumnDimension('B')->setWidth(45);
	$sheet->getColumnDimension('C')->setWidth(45);

	header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
	header("Content-Disposition: attachment;filename=Revisiones.xlsx");
	$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
	$writer->save('php://output');
	exit;
});

/**
 * @OA\Get(
 *     path="/api/getInfoPentestByID",
 *     tags={"Evaluación / EVS"},
 *     summary="Da información específica de un pentest según su ID.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Da información específica de un pentest según su ID.")
 * )
 */
$app->get('/api/getInfoPentestByID', function (Request $request, Response $response) {
	$db = new Pentest("octopus_serv");
	$db_users = new Usuarios(DB_USER);
	$db_activos = new Activos("octopus_serv");
	$parametros = $request->getQueryParams();

	$activosPentest = $db->obtenerActivosPentest($parametros["id"]);
	$issuesPentest = $db->obtenerVulnsPentest($parametros["id"]);
	$user_id = $db->getUserBySolID($parametros["id"]);
	$user_data = $db_users->getUser($user_id);
	$user_mail = $user_data[0]['email'];
	$solicitud_id = $db->getDocumentacionByPentestId($parametros["id"]);
	$documentacion = $solicitud_id[0]['documentacion'];
	$observaciones = $db->getComments($parametros["id"]);

	$data = array();
	$data["activos"] = array();
	$data["vulns"] = array();
	$data["user_email"] = $user_mail;
	$data["documentacion"] = $documentacion;
	$data["observaciones"] = $observaciones;

	foreach ($activosPentest as $activoPentest) {
		$activo = $db_activos->getActivo($activoPentest["id_activo"]);
		if ($activo[0]["tipo"] == 67) {
			array_push($data["activos"], $activo[0]["nombre"]);
		}
	}

	foreach ($issuesPentest as $issuePentest) {
		array_push($data["vulns"], $issuePentest["id_issue"]);
	}

	$response->getBody()->write(json_encode($data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});
/**
 * @OA\Get(
 *     path="/api/reabrirRevision",
 *     tags={"Evaluación / EVS"},
 *     summary="Reabre un revision.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la revision.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Reabre un revision.")
 * )
 */
$app->get('/api/reabrirRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Revision("octopus_serv");
	$db->cambiarStatusRevision($parametros["id"], "1");
	$response->getBody()->write(json_encode("Revision reabierta"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/reabrirPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Reabre un pentest.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Reabre un pentest.")
 * )
 */
$app->get('/api/reabrirPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest("octopus_serv");
	$db->cambiarStatusPentest($parametros["id"], "1");
	$response->getBody()->write(json_encode("Pentest reabierto"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainAllRevisiones",
 *     tags={"Evaluación / EAS"},
 *     summary="Devuelve todos los revision con su información básica.",
 *     @OA\Response(response="200", description="Devuelve todos los revision con su información básica.")
 * )
 */
$app->get('/api/obtainAllRevisiones', function (Request $_, Response $response) {
	$db = new Revision("octopus_serv");
	$revisiones = $db->getRevisiones();
	$stats = array(
		"evaluadas" => 0,
		"abiertas" => 0,
		"identificables" => 0,
		"evaluables" => 0,
	);
	foreach ($revisiones as $revision) {
		switch ($revision["status"]) {
			case 0:
			case 7:
			case 8:
				$stats["evaluadas"]++;
				break;
			case 1:
			case 4:
			case 9:
				$stats["abiertas"]++;
				break;
			case 2:
			case 5:
				$stats["identificables"]++;
				break;
			case 3:
			case 6:
				$stats["evaluables"]++;
				break;
			default:
				break;
		}
	}
	$response_data = array();
	$response_data[] = $stats;
	$response_data[] = $revisiones;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainAllPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Devuelve todos los pentest con su información básica.",
 *     @OA\Response(response="200", description="Devuelve todos los pentest con su información básica.")
 * )
 */
$app->get('/api/obtainAllPentest', function (Request $_, Response $response) {
	$db = new Pentest("octopus_serv");
	$pentests = $db->getPentests();
	$stats = array(
		"evaluadas" => 0,
		"abiertas" => 0,
		"identificables" => 0,
		"evaluables" => 0,
	);
	foreach ($pentests as $pentest) {
		switch ($pentest["status"]) {
			case 0:
			case 7:
			case 8:
				$stats["evaluadas"]++;
				break;
			case 1:
			case 4:
			case 9:
				$stats["abiertas"]++;
				break;
			case 2:
			case 5:
				$stats["identificables"]++;
				break;
			case 3:
			case 6:
				$stats["evaluables"]++;
				break;
			default:
				break;
		}
	}
	$response_data = array();
	$response_data[] = $stats;
	$response_data[] = $pentests;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Devuelve todos los pentest y su información.",
 *     @OA\Response(response="200", description="Devuelve todos los pentest y su información.")
 * )
 */
$app->get('/api/obtenerPentest', function (Request $_, Response $response) {
	$db = new Pentest("octopus_serv");
	$db_activos = new Activos(DB_SERV);
	$pentests = $db->getPentests();
	foreach ($pentests as $index => $pentest) {
		$activos = $db->obtenerActivosPentest($pentest["id"]);
		if (isset($activos[0])) {
			$activo = $db_activos->getActivo($activos[0]["id_activo"]);
			$padres = array(
				"direccion" => getParentescobySistemaId(array($activo[0]), 124),
				"area" => getParentescobySistemaId(array($activo[0]), 123),
				"servicio" => getParentescobySistemaId(array($activo[0]), 42)
			);
			$pentests[$index]["padres"] = $padres;
		} else {
			$activo["nombre"] = "Pentest sin activos";
			$padres = array(
				"direccion" => $activo,
				"area" => $activo,
				"servicio" => $activo
			);
			$pentests[$index]["padres"] = $padres;
		}
	}
	$response->getBody()->write(json_encode($pentests));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerPentestSimple",
 *     tags={"Evaluación / EVS"},
 *     summary="Devuelve todos los pentest y su información.",
 *     @OA\Response(response="200", description="Devuelve todos los pentest y su información básica.")
 * )
 */
$app->get('/api/obtenerPentestSimple', function (Request $_, Response $response) {
	$db = new Pentest("octopus_serv");
	$pentests = $db->getPentests();
	$response->getBody()->write(json_encode($pentests));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getProductosAnalisis",
 *     tags={"Evaluación / ECR"},
 *     summary="Devuelve todas las evaluaciones de pentest mostrando el producto al que se refiere.",
 *     @OA\Response(response="200", description="Devuelve todos los pentest y su información.")
 * )
 */
$app->get('/api/getProductosAnalisis', function (Request $_, Response $response) {
	$db = new DbOperations(DB_SERV);
	$db_activos = new Activos(DB_SERV);
	$evaluaciones = $db->getActivosEvalPentest();
	$array_productos = array();
	$datos_activo = array(
		"nombre_producto" => "",
		"producto_id" => "",
		"nombre_servicio" => "",
		"servicio_id" => "",
		"nombre_activo" => "",
		"activo_id" => "",
		"fecha" => "",
	);
	foreach ($evaluaciones as $evaluacion) {
		$organizacion = "";
		$padres = $db_activos->getFathersNew($evaluacion["activo_id"]);
		$datos_activo["fecha"] = $evaluacion["fecha"];
		$datos_activo["activo_id"] = $evaluacion["activo_id"];
		$datos_activo["nombre_activo"] = $db_activos->getActivo($evaluacion["activo_id"])[0]["nombre"];
		foreach ($padres as $padre) {
			if ($padre["tipo"] == "Servicio de Negocio") {
				$datos_activo["nombre_servicio"] = $padre["nombre"];
				$datos_activo["servicio_id"] = $padre["id"];
			} elseif ($padre["tipo"] == "Producto") {
				$datos_activo["nombre_producto"] = $padre["nombre"];
				$datos_activo["producto_id"] = $padre["id"];
			} elseif ($padre["tipo"] == "Organización") {
				$organizacion = $padre["nombre"];
			}
		}
		if ($organizacion == "Telefónica Innovación Digital") {
			array_push($array_productos, $datos_activo);
		}
	}

	$return = array();
	$return["productos"] = $array_productos;
	$return["count"] = obtenerProductosUnicos($array_productos);
	$response->getBody()->write(json_encode($return));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/insertActivosRevision",
 *     tags={"Evaluación / EAS"},
 *     summary="Inserta los activos de tipo sistema que va a tener la evaluación del revision.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Servicio", type="string", description="Servicio padre de los sistemas."),
 *             @OA\Property(property="Sistema", type="string", description="Sistema al que va a afectar el revision."),
 *             @OA\Property(property="SistemaX", type="string", description="Se añade un campo sistema mas con un número por cada sistema mas incluido. Ejemplo: Sistema1, Sistema2, Sistema3..."),
 *             @OA\Property(property="id", type="string", description="Id del revision."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Asigna los sistemas que va a tener un revision.")
 * )
 */
$app->post('/api/insertActivosRevision', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Revision("octopus_serv");
	$arraySistemas = array();
	array_push($arraySistemas, $parametros["Sistema"]);
	$numSistema = 1;
	while (isset($parametros["Sistema" . strval($numSistema)])) {
		if ($parametros["Sistema" . strval($numSistema)] != "Ninguno" && !in_array($parametros["Sistema" . strval($numSistema)], $arraySistemas)) {
			array_push($arraySistemas, $parametros["Sistema" . strval($numSistema)]);
		}
		$numSistema += 1;
	}
	$db->insertarID($parametros["id"], $arraySistemas);
	//Editamos el estado del revision comoidentificado
	$revision = $db->obtainRevisionFromId($parametros["id"]);
	if ($revision[0]["status"] == 2) {
		$db->cambiarStatusRevision($parametros["id"], "3");
	} elseif ($revision[0]["status"] == 5) {
		$db->cambiarStatusRevision($parametros["id"], "6");
	}
	$response->getBody()->write(json_encode("Se han añadido los sistemas correctamente."));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getRevisiones",
 *     tags={"Evaluación / EAS"},
 *     summary="Devuelve los valores para la gráfica de cumplimiento de bia.",
 *     @OA\Response(response="200", description="Devuelve los valores para la gráfica de cumplimiento de bia.")
 * )
 */
$app->get('/api/getRevisiones', function (Request $_, Response $response) {
	$db = new Revision(DB_SERV);
	$response_data = $db->getRevisions();
	$db_users = new Usuarios(DB_USER);
	foreach ($response_data as &$revision) {
		if (is_null($revision['fecha_final'])) {
			$revision['fecha_final'] = "Sin fecha final prevista";
		}
		$users = $db_users->getUser($revision['user_id']);
		if (isset($users[0]['email'])) {
			$revision['user_email'] = $users[0]['email'];
		} else {
			$revision['user_email'] = "Sin arquitecto asignado";
		}
	}

	// Escribir los datos modificados en la respuesta
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/getIssuesEAS",
 *    tags={"Evaluación / EAS"},
 *    summary="Devuelve las issues de Arquitectura.",
 * 	  @OA\Response(response="200", description="Devuelve las issues de Arquitectura.")
 * )
 */
$app->get('/api/getIssuesEAS', function (Request $request, Response $response) {
	$jira = new JIRA();
	$db = new Revision(DB_SERV);

	$params = $request->getQueryParams();
	$startAt = isset($params['startAt']) ? (int)$params['startAt'] : 0;
	$maxResults = isset($params['maxResults']) ? (int)$params['maxResults'] : 50;

	$issues = $jira->mostrarIssuesEas($startAt, $maxResults, 'CISOCDCOIN');

	if (isset($issues["issues"]) && is_array($issues["issues"])) {
		foreach ($issues["issues"] as $i => $issue) {
			$issueKey = $issue["key"] ?? null;
			if ($issueKey) {
				$reviewName = $db->getReviewNameByIssueKey($issueKey);
				$revisionId = $db->checkIssueKeyExists($issueKey);
				if ($revisionId) {
					$revisionData = $db->obtainRevisionFromId($revisionId);
					if (isset($revisionData[0])) {
						$issues["issues"][$i]["cloudId"] = $revisionData[0]["cloudId"];
						$cloudNameArr = $db->getSuscriptionNameBySusId($revisionData[0]["cloudId"]);
						$issues["issues"][$i]["cloudName"] = isset($cloudNameArr[0]["suscription_name"]) ? $cloudNameArr[0]["suscription_name"] : null;
					}
				}
				if (isset($reviewName)) {
					$issues["issues"][$i]["reviewName"] = "🟢 " . $reviewName;
				} else {
					$issues["issues"][$i]["reviewName"] = "🔴 Sin revisión asociada";
				}
			} else {
				$issues["issues"][$i]["reviewName"] = "🔴 Sin revisión asociada";
			}
		}
	}

	$response->getBody()->write(json_encode($issues));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/generarDocumentoRevision",
 *    tags={"Evaluación / EAS"},
 *    summary="Genera un documento de la revision.",
 *    @OA\Parameter(
 *        name="id",
 *        in="query",
 * 	      required=true,
 * 	  	  description="ID de la revision.",
 * 	      @OA\Schema(type="string")
 *   ),
 *  @OA\Response(response="200", description="Genera un documento de la revision.")
 * )
 */
$app->get('/api/generarDocumentoRevision', function (Request $request, Response $response) {
	$response_data = [ERROR => false];
	$params = $request->getQueryParams();
	$revisionId = $params['revisionId'] ?? null;
	$alertasAsignadas = $params['alertasAsignadas'] ?? null;
	$observaciones = $params['observaciones'] ?? null;
	$mails_copia = isset($params['emails']) ? explode(',', $params['emails']) : [];

	if (empty($revisionId) || !is_numeric($revisionId)) {
		$response_data = [ERROR => true, MESSAGE => 'Parámetro revisionId inválido o faltante'];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	if (empty($alertasAsignadas)) {
		$response_data = [ERROR => true, MESSAGE => 'Debe proporcionar al menos una alerta asignada'];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	try {
		$templatePath = '../plantilla/CDCO-11cert_eas.docx';
		if (!file_exists($templatePath)) {
			$response_data = [ERROR => true, MESSAGE => 'Plantilla no encontrada.'];
			$response->getBody()->write(json_encode($response_data));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(500);
		}

		$generador = new GeneradorDocumentoRevision($templatePath);
		$db = new Revision(DB_SERV);
		$db_users = new Usuarios(DB_USER);
		$jira = new JIRA();

		$informe_enviado = $db->checkInformeEnviado($revisionId);

		$alertasArray = array_filter(array_map('trim', explode(',', $alertasAsignadas)), 'strlen');

		if ($informe_enviado === 0) {
			$user_id = $db->getArquitectoByRevisionID($revisionId);

			if (empty($user_id)) {
				$response_data = [
					ERROR   => true,
					MESSAGE => 'No se encontró un arquitecto asociado a la revisión.'
				];
				$response->getBody()->write(json_encode($response_data));
				return $response
					->withHeader(CONTENT_TYPE, JSON)
					->withStatus(400);
			}

			if (is_array($user_id) && array_key_exists(0, $user_id) && isset($user_id[0]['user_id'])) {
				$architectId = $user_id[0]['user_id'];
			} elseif (is_array($user_id) && isset($user_id['user_id'])) {
				$architectId = $user_id['user_id'];
			} else {
				$architectId = null;
			}

			$user_data = $db_users->getUser($architectId);
			$user_mail = $user_data[0]['email'] ?? '';
			$respProyecto = $db->getResponsableProyecto($revisionId);
			$mail_responsable_proyecto = $jira->obtainReporterMail($respProyecto[0]['resp_proyecto']);

			$mailResponse = $generador->sendMail($revisionId, $alertasArray, $observaciones, $user_mail, $mail_responsable_proyecto, $mails_copia);

			if ($mailResponse[ERROR]) {
				$response_data = [
					ERROR   => true,
					MESSAGE => 'Error al enviar el correo. ' . $mailResponse[MESSAGE]
				];
				$response->getBody()->write(json_encode($response_data));
				return $response
					->withHeader(CONTENT_TYPE, JSON)
					->withStatus(500);
			} else {
				$db->setInformAsSent($revisionId);
				$generador->refillEasDocument($revisionId, $alertasArray, $observaciones);

				$response_data = [
					ERROR   => false,
					MESSAGE => 'Informe generado y enviado exitosamente.'
				];
				$response->getBody()->write(json_encode($response_data));
				return $response
					->withHeader(CONTENT_TYPE, JSON)
					->withStatus(200);
			}
		} else {
			$generador->refillEasDocument($revisionId, $alertasArray);
			$response_data = [
				ERROR   => false,
				MESSAGE => 'Documento generado correctamente.'
			];
			$response->getBody()->write(json_encode($response_data));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(200);
		}
	} catch (Exception $e) {
		error_log('Error al procesar la revisión con ID: ' . $revisionId . '. Mensaje: ' . $e->getMessage());
		$response_data = [
			ERROR   => true,
			MESSAGE => 'Error al procesar la revisión. ' . $e->getMessage()
		];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(500);
	}
});

/**
 * @OA\Get(
 *    path="/api/generarDocumentoPentest",
 *    tags={"EVS"},
 *    summary="Genera un documento de pentest.",
 *    @OA\Parameter(
 *        name="id",
 *        in="query",
 * 	      required=true,
 * 	  	  description="ID del pentest.",
 * 	      @OA\Schema(type="string")
 *   ),
 *  @OA\Response(response="200", description="Genera un documento del pentest.")
 * )
 */
$app->get('/api/generarDocumentoPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest(DB_SERV);
	$db_users = new Usuarios(DB_USER);
	$jira = new JIRA();
	$id = $parametros['id'] ?? null;
	$observaciones = $parametros['observaciones'] ?? null;
	$fecha_creacion = date('Y-m-d H:i:s');
	$req_informe = $db->checkReqInforme($id);
	$informe_enviado = $db->checkInformeEnviado($id);
	$mails_copia = isset($parametros['emails']) ? explode(',', $parametros['emails']) : [];
	$user_id = $db->getUserBySolID($id);
	$response_data = [ERROR => false];

	if (empty($id) || !is_numeric($id)) {
		$response_data = [ERROR => true, MESSAGE => 'ID inválido o faltante'];
		$response->getBody()->write(json_encode($response_data));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(400);
	}

	try {
		$templatePath = '../plantilla/CDCO-11cert_evs.docx';

		if (!file_exists($templatePath)) {
			$response_data = [ERROR => true, MESSAGE => 'Plantilla no encontrada.'];
			$response->getBody()->write(json_encode($response_data));
			return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(500);
		}

		$generador = new GeneradorDocumentoPentest($templatePath);

		if ($informe_enviado === 0 && $req_informe === 1) {
			$user_data = $db_users->getUser($user_id);
			$user_mail = $user_data[0]['email'];
			$responsable_proyecto = $db->getResponsableProyecto($id);
			$mail_responsable_proyecto = $jira->obtainReporterMail($responsable_proyecto[0]['resp_proyecto']);
			$buzones = [
				"desarrolloseguro.cdco@telefonica.com",
				"e.arquitecturaseguridad.cdo@telefonica.com"
			];
			$mailResponse = $generador->sendMail($id, $observaciones, $user_mail, $mail_responsable_proyecto, $mails_copia, $buzones);

			if ($mailResponse[ERROR]) {
				$response_data = [ERROR => true, MESSAGE => 'Error al enviar el correo. ' . $mailResponse[MESSAGE]];
				$response->getBody()->write(json_encode($response_data));
				return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(500);
			} else {
				$db->setInformAsSent($id);
				$db->insertComments($id, $observaciones, $user_id, $fecha_creacion);
				$generador->refillEvsDocument($id, $observaciones);
				$response_data = [ERROR => false, MESSAGE => 'Informe generado y enviado exitosamente.'];
				$response->getBody()->write(json_encode($response_data));
				return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
			}
		} else {
			$db->insertComments($id, $observaciones, $user_id, $fecha_creacion);
			$generador->refillEvsDocument($id, $observaciones);
			$response_data = [ERROR => false, MESSAGE => 'Documento generado correctamente.'];
			$response->getBody()->write(json_encode($response_data));
			return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
		}
	} catch (Exception $e) {
		$response_data = [ERROR => true, MESSAGE => 'Error al procesar el pentest. ' . $e->getMessage()];
		$response->getBody()->write(json_encode($response_data));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(500);
	}
});

/**
 * @OA\Get(
 *     path="/api/getEmails",
 *     tags={"Ususarios"},
 *     summary="Obtiene los correos de los usuarios con permisos en un rol.",
 *     @OA\Parameter(
 *         name="endpointName",
 *         in="query",
 *         required=true,
 *         description="Nombre del endpoint.",
 *         @OA\Schema(type="string")
 *    ),
 *    @OA\Response(response="200", description="Obtiene los correos de los usuarios con permisos en un rol.")
 * )
 */
$app->get('/api/getEmails', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	try {
		if (!isset($parametros['endpointName']) || empty($parametros['endpointName'])) {
			throw new RouteException('No se ha especificado el parámetro endpointName o está vacío.');
		}
		$endpointName = $parametros['endpointName'];

		if ($endpointName != '/evs' && $endpointName != '/eas') {
			throw new RouteException('El endpointName debe ser /evs o /eas.');
		}
		$db_users = new Usuarios(DB_USER);

		if ($endpointName === '/evs') {
			$emails = $db_users->getEmailsByEndpoint($endpointName);
		}
		if ($endpointName === '/eas') {
			if (!isset($parametros['revisionId']) || empty($parametros['revisionId']) || !is_numeric($parametros['revisionId'])) {
				throw new RouteException('No se ha especificado el parámetro revisionId o no es un número válido.');
			}
			$revisionId = $parametros['revisionId'];
			$db_revision = new Revision(DB_SERV);
			$architect_id = $db_revision->getArquitectoByRevisionID($revisionId);

			if (!isset($architect_id[0]['user_id'])) {
				throw new RouteException('No se encontró un arquitecto asociado a la revisión o no existe la revisión con el ID proporcionado.');
			}
			$architectId = $architect_id[0]['user_id'];
			$jira = new JIRA();
			$architect_data = $db_users->getUser($architectId);
			$user_email = $architect_data[0]['email'] ?? '';
			$respProyecto = $db_revision->getResponsableProyecto($revisionId);
			if (!isset($respProyecto[0]['resp_proyecto']) || empty($respProyecto)) {
				throw new RouteException('No se encontró un responsable de proyecto asociado a la revisión.');
			}
			$mail_responsable_proyecto = $jira->obtainReporterMail($respProyecto[0]['resp_proyecto']);
			$emails_eas = $db_users->getEmailsByEndpoint($endpointName);
			$emails = [
				'user_email' => $user_email,
				'mail_responsable_proyecto' => $mail_responsable_proyecto,
				'emails_eas' => $emails_eas
			];
		}
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $e->getMessage();
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}
	$response->getBody()->write(json_encode($emails));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="getRevisionesYPentestActivos",
 *    tags={"KPM"},
 *    summary="Obtiene las revisiones y pentests activos.",
 *    @OA\Response(response="200", description="Obtiene las revisiones y pentests activos.")
 * )
 */
$app->get('/api/getRevisionesYPentestActivos', function (Request $_, Response $response) {
	$db = new Revision(DB_SERV);
	$db_activos = new Activos(DB_SERV);
	$revisiones = $db->getRevisions();
	$revisionesSinProducto = array();
	foreach ($revisiones as &$revision) {
		$revisionSinProducto = false;
		$activosRevision = $db->obtenerActivosRevision($revision['id']);
		foreach ($activosRevision as &$activo) {
			$activoData = $db_activos->getActivo($activo['id_activo']);
			$activo = $activoData[0];
			if ($activo["tipo"] == 67) {
				$revisionSinProducto = true;
				break;
			}
		}
		if (!$revisionSinProducto) {
			$revision['activos'] = $activosRevision;
			$revisionesSinProducto[] = $revision;
		}
	}
	$db_pentest = new Pentest(DB_SERV);
	$pentests = $db_pentest->getPentests();
	$pentestsSinProducto = array();
	foreach ($pentests as &$pentest) {
		$pentestSinProducto = false;
		$activosPentest = $db_pentest->obtenerActivosPentest($pentest['id']);
		foreach ($activosPentest as &$activo) {
			$activoData = $db_activos->getActivo($activo['id_activo']);
			$activo = $activoData[0];
			if ($activo["tipo"] == 67) {
				$pentestSinProducto = true;
			}
		}
		if (!$pentestSinProducto) {
			$pentest['activos'] = $activosPentest;
			$pentestsSinProducto[] = $pentest;
		}
	}
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Revisiones y pentests activos obtenidos correctamente.";
	$response_data["pruebas"]["revisiones"] = $revisionesSinProducto;
	$response_data["pruebas"]["pentests"] = $pentestsSinProducto;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="updatePentestYArquitecturaProductos",
 *    tags={"KPM"},
 *    summary="Actualiza los pentests y arquitectura de productos.",
 *    @OA\Response(response="200", description="Actualiza los pentests y arquitectura de productos.")
 * )
 */
$app->get('/api/updatePentestYArquitecturaProductos', function (Request $_, Response $response) {
	$db = new Revision(DB_SERV);
	$db_activos = new Activos(DB_SERV);
	$revisiones = $db->getRevisions();
	foreach ($revisiones as &$revision) {
		$revisionSinProducto = false;
		$activosRevision = $db->obtenerActivosRevision($revision['id']);
		foreach ($activosRevision as &$activo) {
			$activoData = $db_activos->getActivo($activo['id_activo']);
			$activo = $activoData[0];
			if ($activo["tipo"] == 67) {
				$revisionSinProducto = true;
				break;
			}
		}
		if (!$revisionSinProducto) {
			foreach ($activosRevision as &$activo) {
				if ($activo["tipo"] == 33 || $activo["tipo"] == 42) {
					$producto = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
					if (isset($producto[0])) {
						$db->insertActivosRevision($revision['id'], $producto[0]['id']);
						break;
					}
				}
			}
		}
	}
	$db_pentest = new Pentest(DB_SERV);
	$pentests = $db_pentest->getPentests();
	foreach ($pentests as &$pentest) {
		$pentestSinProducto = false;
		$activosPentest = $db_pentest->obtenerActivosPentest($pentest['id']);
		foreach ($activosPentest as &$activo) {
			$activoData = $db_activos->getActivo($activo['id_activo']);
			$activo = $activoData[0];
			if ($activo["tipo"] == 67) {
				$pentestSinProducto = true;
			}
		}
		if (!$pentestSinProducto) {
			foreach ($activosPentest as &$activo) {
				if ($activo["tipo"] == 33 || $activo["tipo"] == 42) {
					$producto = $db_activos->getFathersNewByTipo($activo['id'], "Producto");
					if (isset($producto[0])) {
						$db_pentest->insertActivosPentest($pentest['id'], $producto[0]['id']);
						break;
					}
				}
			}
		}
	}
	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Productos de pentests y revisiones actualizados correctamente correctamente.";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="getKpmReportersMails",
 *    tags={"KPM"},
 *    summary="Obtiene los correos de los reporteros de KPM.",
 *    @OA\Response(response="200", description="Obtiene los correos de los reporteros de KPM.")
 * )
 */
$app->get('/api/getKpmReportersMails', function (Request $_, Response $response) {
	$db = new Usuarios(DB_USER);
	$emails = $db->getEmailsByEndpoint('/reportarkpms');
	$response->getBody()->write(json_encode($emails));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/crearRevision",
 *     tags={"Evaluación / EAS"},
 *     summary="Crea una revisión.",
 *     @OA\Parameter(
 *         name="tipo",
 *         in="query",
 *         required=true,
 *         description="Tipo del pentest a crear.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Nombre", type="string", description="Nombre de la revision."),
 *             @OA\Property(property="Responsable", type="string", description="Responsable de la revision."),
 *             @OA\Property(property="Descripcion", type="string", description="Descripción de la revision."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un pentest.")
 * )
 */
$app->post('/api/crearRevision', function (Request $request, Response $response) {
	// Obtener los parámetros de la solicitud y el ID del cloud
	$token = $request->getAttribute(TOKEN);
	$query = $request->getQueryParams();
	$parameters = $request->getParsedBody();

	$response_data = [];

	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Revision creada correctamente.";

	if (empty($parameters["ResponsableProy"])) {
		$parameters["ResponsableProy"] = "Ninguno";
	}
	$parameters["Fecha_inicio"] = date("Y-m-d");

	$db = new Usuarios(DB_USER);
	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {
		$parameters['user_id'] = $usuario[0]['id'];
	}

	$parameters["Nombre"] = getRevisionName($query["idCloud"], $parameters);
	// Crear la revisión en la base de datos
	$db = new Revision(DB_SERV);
	$revision = $db->createRevision($parameters, $query["idCloud"]);

	if ($revision) {
		$error = false;
	} else {
		$error = true;
	}

	if (!$error) {
		// Asignar activos a la revisión
		$error = setActivosRevision($parameters, $query["idCloud"]);
	}

	if ($error) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se han podido asignar los activos a la revision.";
	} else {
		$response_data["revision"] = $revision;
	}
	// Devolver la respuesta en formato JSON
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/crearRevisionSinActivos",
 *     tags={"Evaluación / EAS"},
 *     summary="Crea una revisión sin asociación a activos.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Nombre", type="string", description="Nombre de la revision."),
 *             @OA\Property(property="ResponsableProy", type="string", description="Responsable de la revision."),
 *             @OA\Property(property="Descripcion", type="string", description="Descripción de la revision."),
 *             @OA\Property(property="AreaServ", type="string", description="Área o servicio de la revisión."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una revisión sin asociación a activos.")
 * )
 */
$app->post('/api/crearRevisionSinActivos', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$parameters = $request->getParsedBody();

	$response_data = [];
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Revision creada correctamente.";

	if (empty($parameters["ResponsableProy"])) {
		$parameters["ResponsableProy"] = "Ninguno";
	}

	$parameters["Fecha_inicio"] = date("Y-m-d");

	$db = new Usuarios(DB_USER);
	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {
		$parameters['user_id'] = $usuario[0]['id'];
	}

	$db = new Revision(DB_SERV);
	$revision = $db->createRevisionWithoutActivos($parameters, null);

	if ($revision) {
		$response_data["revision"] = $revision;
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Error al crear la revisión.";
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/crearRelacionSuscripcion",
 *     tags={"Evaluación / EAS"},
 *     summary="Crea una relación.",
 *     @OA\Parameter(
 *         name="tipo",
 *         in="query",
 *         required=true,
 *         description="Asocia una suscripción a un activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="ID Activo", type="int", description="ID del activo a relacionar"),
 *             @OA\Property(property="Nombre de la suscripción", type="string", description="Nombre de la suscripción a relacionar."),
 *             @OA\Property(property="ID de la suscripción", type="string", description="ID de la suscripción a relacionar."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una relación.")
 * )
 */
$app->post('/api/crearRelacionSuscripcion', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Revision(DB_SERV);

	$resultado = $db->insertSuscriptionRelation($parametros);

	$response->getBody()->write(json_encode($resultado));

	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getSuscriptionRelations",
 *     tags={"Evaluación / EAS"},
 *     summary="Obtiene las relaciones de suscripciones con activos.",
 *     @OA\Response(response="200", description="Obtiene las relaciones de suscripciones con activos.")
 * )
 */
$app->get('/api/getSuscriptionRelations', function (Request $_, Response $response) {
	$db = new Revision(DB_SERV);
	$relations = $db->getSuscriptionRelations();
	$response->getBody()->write(json_encode(["relations" => $relations]));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/deleteSuscriptionRelations",
 *    tags={"Evaluación / EAS"},
 *    summary="Elimina una relación de suscripción.",
 *    @OA\Parameter(
 * 	   name="id",
 * 	   in="query",
 * 	   required=true,
 * 	   description="ID de la suscripcion a eliminar.",
 * 	   @OA\Schema(type="string")
 * *    ),
 *   @OA\Response(response="200", description="Elimina una relación de suscripción.")
 * )
 */
$app->post('/api/deleteSuscriptionRelations', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Revision(DB_SERV);
	$resultado = $db->deleteSuscriptionRelation($parametros["suscription_id"]);
	$response->getBody()->write(json_encode($resultado));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/editSuscriptionRelations",
 *    tags={"Evaluación / EAS"},
 *    summary="Edita una relación de suscripción.",
 *    @OA\Parameter(
 * 	   name="id",
 * 	   in="query",
 * 	   required=true,
 * 	   description="ID de la suscripcion a editar.",
 * * 	   @OA\Schema(type="string")
 * *    ),
 *   @OA\RequestBody(
 * 	   required=true,
 * 	   @OA\JsonContent(
 * * 	      @OA\Property(property="ID Activo", type="int", description="ID del activo a relacionar"),
 * * 	      @OA\Property(property="Nombre de la suscripción", type="string", description="Nombre de la suscripción a relacionar."),
 * * 	      @OA\Property(property="ID de la suscripción", type="string", description="ID de la suscripción a relacionar."),
 * * 	   )
 * *    ),
 * *   @OA\Response(response="200", description="Edita una relación de suscripción.")
 * )
 */
$app->post('/api/editSuscriptionRelations', function (Request $request, Response $response) {
	$params = $request->getParsedBody();
	$suscriptionId = $params['suscription_id'] ?? null;
	$activoId = $params['id_activo']       ?? null;

	if (!$suscriptionId || !$activoId) {
		$result = [
			'error' => true,
			'message' => 'Faltan parámetros obligatorios: suscription_id y id_activo.'
		];
	} else {
		$db = new Revision(DB_SERV);
		$result = $db->updateSuscriptionRelation($suscriptionId, $activoId);
	}

	$response->getBody()->write(json_encode($result));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *    path="/api/getBrothers",
 *    tags={"Activos"},
 *    summary="Devuelve los hermanos de un activo.",
 *    @OA\Parameter(
 * 	   name="id",
 * 	   in="query",
 * 	   required=true,
 * 	   description="ID del activo.",
 * * 	   @OA\Schema(type="string")
 * *    ),
 *  @OA\Response(response="200", description="Devuelve los hermanos de un activo.")
 * )
 */
$app->get('/api/getBrothers', function (Request $request, Response $response) {
	$params = $request->getQueryParams();
	$idActivo = $params['id_activo'] ?? null;
	$dbActivos = new Activos(DB_SERV);

	if (!$idActivo) {
		$payload = [
			'error'   => true,
			'message' => 'Falta el parámetro id_activo'
		];
	} else {
		$padres = $dbActivos->getFathers(['id' => $idActivo]);

		if (empty($padres) || !isset($padres[0]['id'])) {
			$hermanos = [];
		} else {
			$parentId = $padres[0]['id'];
			$hermanos = $dbActivos->getBrothers($parentId, $idActivo);
		}

		$payload = $hermanos;
	}

	$response->getBody()->write(json_encode($payload));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerReporterID",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Obtiene el ID de un usuario de Jira.",
 *     @OA\Parameter(
 *         name="reporterName",
 *         in="query",
 *         required=true,
 *         description="Nombre del usuario a buscar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene el ID de un usuario de Jira.")
 * )
 */
$app->get('/api/obtenerReporterID', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$response_data = $jira->obtainReporterID($parametros["reporterName"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtainUsers",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Obtiene los usuarios de un proyecto de Jira.",
 *     @OA\Parameter(
 *         name="proyecto",
 *         in="query",
 *         required=true,
 *         description="Nombre del proyecto.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene los usuarios de un proyecto de Jira.")
 * )
 */
$app->get('/api/obtainUsers', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$response_data = $jira->obtainUsers($parametros["proyecto"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/gestionarPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Añade una vulnerabilidad a un Pentest.",
 *     @OA\Parameter(
 *         name="vuln",
 *         in="query",
 *         required=true,
 *         description="Key de la vulnerabilidad de Jira.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="pentest",
 *         in="query",
 *         required=true,
 *         description="ID del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Añade una vulnerabilidad a un Pentest.")
 * )
 */
$app->get('/api/gestionarPentest', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$parametros["vuln"] = urldecode($parametros["vuln"]);
	if ($parametros["pentest"] != "Ninguno") {
		$response_data = $jira->gestionarPentest($parametros);
		$response->getBody()->write(json_encode($response_data));
	} else {
		$response->getBody()->write(json_encode("Sin pentest"));
	}
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/actualizarStatus",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Cambia el estado de una issue de Jira específica.",
 *     @OA\Parameter(
 *         name="accion",
 *         in="query",
 *         required=true,
 *         description="Acción a realizar sobre la issue de Jira. 'Abrir' para abrir, 'Cerrar' para cerrar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="key",
 *         in="query",
 *         required=true,
 *         description="ID de la issue.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Cambia el estado de una issue de Jira específica.")
 * )
 */


$app->get('/api/actualizarStatus', function (Request $request, Response $response) {
	$jira = new JIRA();
	$pentest = new Pentest(DB_SERV);
	$prisma = new PrismaCloudAPI();
	$parametros = $request->getQueryParams();
	$issue = $jira->obtenerIssue($parametros["key"]);
	$tipo = $issue["issues"][0]["fields"]["customfield_12611"]["value"] ?? null;

	if ($tipo === "Pentesting") {
		$datos_pentest = $pentest->getPentestsIssues($parametros["key"]);
		if (isset($datos_pentest[0])) {
			if ($parametros["accion"] === "cerrar" || $parametros["accion"] === "abrir") {
				$response_data = $jira->updateStatus($parametros["accion"], $parametros["key"]);
			}
			if ($datos_pentest[0]["status"] == 0) {
				$pentest->cambiarStatusPentest($datos_pentest[0]["id_pentest"], 4);
			}
		}
	} elseif ($tipo === "Architecture Review") {
		$revision = new Revision(DB_SERV);
		$datos_revision = $revision->getRevisionesIssues($parametros["key"]);
		if (isset($datos_revision[0])) {
			$id_revision = $datos_revision[0]["id_revision"];
			if ($parametros["accion"] === "cerrar") {
				$todasClose = true;
				$noResueltas = [];
				foreach ($datos_revision as $issueAlert) {
					$alert = $issueAlert["id_alert"];
					$alertaReportada = json_decode($prisma->getPrismaAlertInfo($alert), true);
					if (!isset($alertaReportada["status"]) || ($alertaReportada["status"] !== "resolved") && ($alertaReportada["status"] !== "dismissed")) {
						$todasClose = false;
						$noResueltas[] = $alert;
						break;
					}
				}
				$todasClose = true;
				if ($todasClose) {
					$response_data = $jira->updateStatus($parametros["accion"], $parametros["key"]);
					if ($datos_revision[0]["status"] == 0) {
						$revision->cambiarStatusRevision($id_revision, 4);
					}
				} else {
					$response_data = [
						"error" => true,
						"message" => "Todas las alertas deben estar cerradas (resolved) para cerrar la issue.<br>
						 Alertas no cerradas: " . implode(", ", $noResueltas)
					];
				}
			} elseif ($parametros["accion"] === "abrir") {
				$response_data = $jira->updateStatus($parametros["accion"], $parametros["key"]);
				if ($datos_revision[0]["status"] == 0) {
					$revision->cambiarStatusRevision($id_revision, 4);
				}
			}
		}
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/enviarComentario",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Envía un comentario a una issue de Jira específica.",
 *     @OA\Parameter(
 *         name="jiraKey",
 *         in="query",
 *         required=true,
 *         description="ID de la issue.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="comentario",
 *         in="query",
 *         required=true,
 *         description="String que contiene el comentario a enviar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Envía un comentario a una issue de Jira específica.")
 * )
 */
$app->get('/api/enviarComentario', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$response_data = $jira->enviarComentarios($parametros["jiraKey"], $parametros["comentario"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerComentarios",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Obtiene los comentarios de una issue de Jira específica.",
 *     @OA\Parameter(
 *         name="jiraKey",
 *         in="query",
 *         required=true,
 *         description="Key de la issue de Jira.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene los comentarios de una issue de Jira específica.")
 * )
 */
$app->get('/api/obtenerComentarios', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$response_data = $jira->obtenerComentarios($parametros["jiraKey"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerIssue",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Obtiene información de una issue de Jira específica.",
 *     @OA\Parameter(
 *         name="jiraKey",
 *         in="query",
 *         required=true,
 *         description="Key de la issue de Jira.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Obtiene información de una issue de Jira específica.")
 * )
 */
$app->get('/api/obtenerIssue', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	$response_data = $jira->obtenerIssue($parametros["jiraKey"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/delIssue",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Elimina una issue de Jira.",
 *     @OA\Parameter(
 *         name="jiraKey",
 *         in="query",
 *         required=true,
 *         description="Key de la issue de Jira.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina una issue de Jira.")
 * )
 */
$app->get('/api/delIssue', function (Request $request, Response $response) {
	$jira = new JIRA();
	$db = new Revision(DB_SERV);

	$parametros = $request->getQueryParams();
	$issueReview = $db->checkIssueKeyExists($parametros["key"]);
	if ($issueReview) {
		$db->delIssueFromRevision($parametros["key"], $issueReview);
	}
	$response_data = $jira->eliminarIssue($parametros["key"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/obtenerCampos",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Devuelve los campos de creación de issues de Jira.",
 *     @OA\Response(response="200", description="Devuelve los campos de creación de issues de Jira.")
 * )
 */
$app->get('/api/obtenerCampos', function (Request $_, Response $response) {
	$jira = new JIRA();
	$response_data = $jira->obtenerCampos();
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/mostrarIssues",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Devuelve las issues de Jira.",
 *     @OA\Parameter(
 *         name="start",
 *         in="query",
 *         required=true,
 *         description="Posición desde la que queremos obtener las issues.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve las issues de Jira.")
 * )
 */
$app->get('/api/mostrarIssues', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getQueryParams();
	if (isset($parametros["start"])) {
		$response_data = $jira->mostrarIssues($parametros["start"], 50);
	} else {
		$response_data = $jira->mostrarIssues(0, 50);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/refreshToken",
 *     tags={"Activos"},
 *     summary="Hace un refresh del token.",
 *    @OA\Response(response="200", description="Hace un refresh del token.")
 * )
 */
$app->get('/api/refreshToken', function (Request $_, Response $response) {
	$prediccion = [
		"Según la luna y el sol hoy es un buen día para empezar algo nuevo.",
		"Según la luna y el sol la suerte estará de tu lado en los negocios.",
		"Según la luna y el sol un amigo cercano te dará una sorpresa.",
		"Según la luna y el sol es un buen momento para reflexionar sobre tus metas.",
		"Según la luna y el sol el amor está en el aire, mantén los ojos abiertos.",
		"Según la luna y el sol tendrás una oportunidad única, no la dejes pasar.",
		"Según la luna y el sol la salud será tu prioridad hoy, cuídate.",
		"Según la luna y el sol un viaje inesperado podría estar en tu futuro.",
		"Según la luna y el sol la creatividad fluirá, aprovecha para crear algo nuevo.",
		"Según la luna y el sol hoy es un día perfecto para resolver conflictos."
	];

	$response_data = $prediccion[array_rand($prediccion)];

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getActivosByNombre",
 *     tags={"Activos"},
 *     summary="Devuelve la información de un activo pasándole un nombre como argumento.",
 *     @OA\Parameter(
 *         name="search",
 *         in="query",
 *         required=true,
 *         description="Nombre del activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Devuelve la información de un activo pasándole un nombre como argumento.")
 * )
 */
$app->get('/api/getActivosByNombre', function (Request $request, Response $response) {
	$busqueda = $request->getQueryParams();
	global $error;
	if (isset($busqueda['search'])) {
		$db = new Activos(DB_SERV);
		$response_data[ERROR] = false;
		$response_data['activos'] = $db->getActivoByNombre('%' . $busqueda['search'] . '%');
	} else {
		$code = 204;
		$response_data[ERROR] = $error->getErrorForCode($code);
		$response_data[MESSAGE] = $error->getMessageForCode($code);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/auth",
 *     tags={"Autenticación"},
 *     summary="Se encarga de comprobar la autenticación con usuarios de o365.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="email", type="string", description="Email del usuario."),
 *             @OA\Property(property="password", type="string", description="Password del usuario."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Se encarga de comprobar la autenticación con usuarios de o365.")
 * )
 */
$app->post('/auth', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();

	// Detectar si es v2 o v3 según la solicitud
	$isV2 = isset($parametros['recaptcha_v2']) && $parametros['recaptcha_v2'] === 'true';
	$recaptcha_token = $isV2 ? $parametros['g-recaptcha-response'] : $parametros['recaptcha_response'];

	$captchaResult = isHuman($recaptcha_token, $isV2);

	switch ($captchaResult) {
		case 'captcha_error':
			return handleCaptchaResponse($response, true, "Error de validación del captcha, vuelva a intentarlo.");
		case 'captcha_failed':
			return handleCaptchaResponse($response, true, "El reCAPTCHA ha fallado, vuelva a intentarlo.");
		case 'suspicious':
			if (!$isV2) {
				return handleCaptchaResponse($response, true, "Sospechoso. Requiere reCAPTCHA v2.", ["recaptcha_v2" => true]);
			}
			break;
		default:
			break;
	}

	$email = $parametros['email'] ?? null;
	$authpass = $parametros['password'] ?? null;


	if ($email !== "") {
		// Validar que los campos de email y password estén presentes
		if (empty($email) || !isset($authpass) || empty($authpass)) {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = "Error de autenticación. Faltan campos.";
			$response->getBody()->write(json_encode($response_data));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(200);
		}

		// Validar credenciales de usuario
		$db = new Usuarios(DB_USER);
		$user = $db->authUser($email);

		if (!isset($user[0]) || !password_verify($authpass, $user[0]["password"])) {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = "Error de autenticación. Email o password incorrectos.";
			$response->getBody()->write(json_encode($response_data));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(200);
		}
		// Identificación del cliente
		$aud = '';
		$aud .= rawurlencode($_SERVER['HTTP_USER_AGENT']);
		$aud .= gethostname();

		// Creación del token JWT
		$jwt = [
			"iss" => "https://11certools.cisocdo.com",
			"aud" => $aud,
			"iat" => time(),
			"exp" => time() + 3600,
			"jti" => bin2hex(random_bytes(16)),
			'samesite' => 'Strict',
			"data" => $user[0]["id"],
		];
		$token = new TokenEncryptor();
		$token->encrypt(json_encode($jwt), "AES-256-CBC", "");
		// Almacenamiento del token en una cookie y actualización del token en la base de datos
		$response_data[ERROR] = false;
		$response_data["url"] = "./home";
		$response_data[MESSAGE] = "Identificado correctamente";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		// TENANT Telefonica
		$tenant = "9744600e-3e04-492e-baa1-25ec245c6f10";
		$client_id = "{client_id_o365}";
		$client_secret = "{client_secret_o365}";
		$callback = "https://11certools.cisocdo.com/auth";
		$scopes = ["openid", "profile"];
		$microsoft = new Auth($tenant, $client_id, $client_secret, $callback, $scopes);
		$url = $microsoft->getAuthUrl();
		$response_data[ERROR] = false;
		$response_data["url"] = $url;
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Post(
 *     path="/api/modificarAppSDLC",
 *     tags={"Evaluación / EVS / SDLC"},
 *     summary="Modifica una aplicación de SDLC.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la App de SDLC.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Comentarios", type="string", description="Comentarios de la App de SDLC."),
 *             @OA\Property(property="CMM", type="string", description="CMM de la App de SDLC."),
 *             @OA\Property(property="Analisis", type="string", description="Análisis de la App de SDLC."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Modifica una aplicación de SDLC.")
 * )
 */
$app->post('/api/modificarAppSDLC', function (Request $request, Response $response) {
	$db = new Pentest(DB_SERV);
	$id =  $request->getQueryParams();
	$parametros = $request->getParsedBody();
	$parametros["id"] = $id["id"];
	$db->modificarAppSDLC($parametros);
	$response->getBody()->write(json_encode("App modificada"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/createToken",
 *     tags={"Autenticación"},
 *     summary="Crea un token de usuario.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="tokenExpiration", type="integer", description="Días de expiración para el token."),
 *             @OA\Property(property="tokenName", type="string", description="Nombre para el token.")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Token creado correctamente.")
 * )
 */
$app->post('/api/createToken', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$token = $request->getAttribute(TOKEN);

	$response_data = [];
	$response_data[ERROR] = false;
	if (isset($parametros["tokenExpiration"])) {
		$expired = time() + $parametros["tokenExpiration"] * 24 * 60 * 60;
	}
	if (isset($parametros["tokenName"])) {
		$name = $parametros["tokenName"];
	}
	$db_users = new Usuarios(DB_USER);
	$respuesta = $db_users->createTokensUser($token["data"], $name, $expired);
	$response_data[MESSAGE] = "Token creado correctamente.";
	$response_data["token"] = $respuesta;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteToken",
 *     tags={"Autenticación"},
 *     summary="Elimina un token de usuario.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="tokenId", type="string", description="ID del token a eliminar.")
 *         )
 *     ),
 *     @OA\Response(response=200, description="Token eliminado correctamente.")
 * )
 */
$app->post('/api/deleteToken', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;
	if (isset($parametros["tokenId"])) {
		$db_users = new Usuarios(DB_USER);
		$db_users->deleteTokenUser($token["data"], $parametros["tokenId"]);
		$response_data[MESSAGE] = "Token eliminado correctamente.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se ha encontrado el token.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
});

/**
 * @OA\Post(
 *    path="/api/saveEvalOsa",
 *    tags={"EAS"},
 *    summary="Envía una evaluación de OSA.",
 *    @OA\RequestBody(
 *        required=true,
 *    @OA\JsonContent(
 * 		@OA\Property(property="revision_id", type="string", description="ID de la evaluación."),
 * 		@OA\Property(property="osa", type="string", description="OSA de la evaluación."),
 * 		@OA\Property(property="valor", type="string", description="Valor del OSA."),
 * 	)
 * ),
 * @OA\Response(response="200", description="Envía una evaluación de OSA.")
 * )
 */
$app->post('/api/saveEvalOsa', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	if (!isset($parametros["revision_id"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se encuentra el ID de la revisión.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
	$db = new DbOperations(DB_SERV);
	$db->saveEvalOsa($parametros);
	$response_data[MESSAGE] = "Evaluación OSA guardada correctamente.";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Post(
 *     path="/api/changeRelacion",
 *     tags={"Evaluación / EVS / SDLC"},
 *     summary="Modifica la relación de un activo.",
 *     @OA\Parameter(
 *         name="hijo",
 *         in="query",
 *         required=true,
 *         description="ID del hijo de la relacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="oldPadre",
 *         in="query",
 *         required=true,
 *         description="ID del padre antiguo de la relacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="padre",
 *         in="query",
 *         required=true,
 *         description="ID del padre nuevo de la relacion.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Modifica la relación de un activo.")
 * )
 */
$app->post('/api/changeRelacion', function (Request $request, Response $response) {
	$created = false;
	$db = new activos("octopus_serv");
	$logs = new Logs("octopus_logs");
	$parametros = $request->getParsedBody();
	$activo["id"] = $parametros["hijo"];
	$padres = $db->getFathersNew($parametros["padre"]);
	foreach ($padres as $padre) {
		if ($padre["id"] == $parametros["hijo"]) {
			$response->getBody()->write(json_encode("Error. Relación sin cambiar por bucle infinito."));
			return $response
				->withHeader(CONTENT_TYPE, JSON)
				->withStatus(200);
		}
	}
	$token = $request->getAttribute(TOKEN);
	$db_users = new Usuarios(DB_USER);
	$user = $db_users->getUser($token['data']);
	$user = $user[0]["id"];
	if ($parametros["oldPadre"] != "Ninguno") {
		$db->deleteRelacion($parametros["hijo"], $parametros["oldPadre"]);
	} else {
		//La idea de hacer esto es comprobar que no haga relaciones que ya existen.
		$padres = $db->getFathers($activo);
		foreach ($padres as $padre) {
			if ($parametros["padre"] == $padre["id"]) {
				$created = true;
				break;
			}
		}
	}

	if (!$created) {
		$db->addRelation($parametros["hijo"], $parametros["padre"]);
		$logs->addLogChangeRelation($parametros, $user);
	}
	$response->getBody()->write(json_encode("Relacion cambiada"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/eliminarAppSDLC",
 *     tags={"Evaluación / EVS / SDLC"},
 *     summary="Elimina una aplicación de SDLC.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la App de SDLC.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina una aplicación de SDLC.")
 * )
 */
$app->post('/api/eliminarAppSDLC', function (Request $request, Response $response) {
	$db = new Pentest("octopus_serv");
	$params = $request->getQueryParams();

	if (!isset($params["id"]) || !isset($params["app"])) {
		$response->getBody()->write(json_encode(['error' => 'Faltan parámetros requeridos.']));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(400);
	}

	$id = $params["id"];
	$app = $params["app"];

	$kiuwan_id = ($app === "Kiuwan") ? ($params["kiuwan_id"] ?? null) : null;

	try {
		$db->eliminarAppSDLC($id, $kiuwan_id, $app);

		$response->getBody()->write(json_encode("App eliminada"));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
	} catch (Exception $e) {
		$response->getBody()->write(json_encode(['error' => 'Error eliminando la aplicación: ' . $e->getMessage()]));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(500);
	}
});

/**
 * @OA\Post(
 *     path="/api/newKPM",
 *     tags={"Kpms"},
 *     summary="Elimina una aplicación de SDLC.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la App de SDLC.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina una aplicación de SDLC.")
 * )
 */
$app->post('/api/newKPM', function (Request $request, Response $response) {
	$db = new KPMs(DB_KPMS);
	$parametros = $request->getParsedBody();
	if (strpos($parametros["numeroKPM"], "KPM") === 0) {
		$db->newKPMs($parametros);
		$response->getBody()->write(json_encode("KPM"));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$response->getBody()->write(json_encode("Error. El KPM no empieza por 'KPM'"));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(500);
	}
});

/**
 * @OA\Post(
 *     path="/api/crearAppSDLC",
 *     tags={"Evaluación / EVS / SDLC"},
 *     summary="Crea una aplicación en SDLC.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Areas", type="object", description="Array con todas las áreas de las que queremos obtener las métricas."),
 *         )
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Servicio", type="string", description="Servicio de la App de SDLC."),
 *             @OA\Property(property="Aplicación", type="string", description="Aplicación de la App de SDLC."),
 *             @OA\Property(property="Analisis", type="string", description="Análisis de la App de SDLC."),
 *             @OA\Property(property="CMM", type="string", description="CMM de la App de SDLC."),
 *             @OA\Property(property="Comentarios", type="string", description="Comentarios de la App de SDLC."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una aplicación en SDLC.")
 * )
 */
$app->post('/api/crearAppSDLC', function (Request $request, Response $response) {
	$db = new Pentest("octopus_serv");
	$parametros = $request->getParsedBody();

	$appExistente = $db->modificarSDLC($parametros);

	if (!$appExistente) {
		$db->addSdlc($parametros);

		if ($parametros["app"] === "Kiuwan") {
			$kiuwanData = $db->getKiuwanData();
			$kiuwanSlotNombre = $kiuwanData[0]["app_name"] ?? "N/A";

			$aplicacion = [
				"Direccion" => $parametros["Direccion"],
				"Area" => $parametros["Area"],
				"Producto" => $parametros["Producto"],
				"kiuwan_id" => $parametros["kiuwan_id"],
				"kiuwan_slot" => $kiuwanSlotNombre,
				"sonarqube_slot" => null,
				"fecha_analisis_sonar" => null,
				"CMM" => $parametros["CMM"],
				"Analisis" => $parametros["Analisis"],
				"Comentarios" => $parametros["Comentarios"] ?? "",
				"url_sonar" => $parametros["url_sonar"] ?? "",
				"fecha_analisis_kiuwan" => $parametros["fecha_analisis_kiuwan"] ?? "",
				"Created" => "Yes",
				"Error" => "No",
			];
		} elseif ($parametros["app"] === "Sonarqube") {
			$aplicacion = [
				"Direccion" => $parametros["Direccion"],
				"Area" => $parametros["Area"],
				"Producto" => $parametros["Producto"],
				"sonarqube_slot" => $parametros["sonarqube_slot"],
				"kiuwan_id" => null,
				"kiuwan_slot" => null,
				"CMM" => $parametros["CMM"],
				"Analisis" => $parametros["Analisis"],
				"Comentarios" => $parametros["Comentarios"] ?? "",
				"url_sonar" => $parametros["url_sonar"],
				"fecha_analisis_kiuwan" => null,
				"fecha_analisis_sonar" => $parametros["fecha_analisis_sonarqube"],
				"Created" => "Yes",
				"Error" => "No",
			];
		}
	} else {
		$aplicacion = [
			"Created" => "No",
			"Error" => "No",
		];
	}

	$response->getBody()->write(json_encode($aplicacion));
	return $response->withHeader('Content-Type', JSON)->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editarKPMTabla",
 *     tags={"Kpms"},
 *     summary="Edita un KPM.",
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         required=true,
 *         description="id del KPM a eliminar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Edita un KPM.")
 * )
 */
$app->post('/api/editarKPMTabla', function (Request $request, Response $response) {
	$db = new KPMs("octopus_kpms");
	$parametros =  $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data["message"] = "KPM eliminado correctamente.";
	if (!$parametros["idKPM"] || !$parametros["nombre"] || !$parametros["descripcion_corta"] || !$parametros["descripcion_larga"] || !$parametros["grupo"]) {
		$response_data[ERROR] = true;
		$response_data["message"] = "Error. No se ha encontrado el KPM.";
	} else {
		$db->editKPM($parametros["idKPM"], $parametros["nombre"], $parametros["descripcion_larga"], $parametros["descripcion_corta"], $parametros["grupo"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteKPMTabla",
 *     tags={"Kpms"},
 *     summary="Elimina un KPM.",
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         required=true,
 *         description="id del KPM a eliminar.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina un KPM.")
 * )
 */
$app->post('/api/deleteKPMTabla', function (Request $request, Response $response) {
	$db = new KPMs("octopus_kpms");
	$parametros =  $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data["message"] = "KPM eliminado correctamente.";
	if (!$parametros["idKPM"]) {
		$response_data[ERROR] = true;
		$response_data["message"] = "Error. No se ha encontrado el KPM.";
	} else {
		$db->deleteKPM($parametros["idKPM"]);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/addKPMFormulario",
 *     tags={"Kpms"},
 *     summary="Añade un KPM al formulario.",
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         required=true,
 *         description="Nombre del KPM a añadir.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Añade un KPM al formulario.")
 * )
 */
$app->post('/api/addKPMFormulario', function (Request $request, Response $response) {
	$db = new activos("octopus_kpms");
	$parametros =  $request->getQueryParams();
	$db->editarKPMFormulario($parametros, 1);
	$response->getBody()->write(json_encode("KPM añadido"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteRelacionReporter",
 *     tags={"Kpms"},
 *     summary="Elimina una relación de reporter.",
 *     @OA\Parameter(
 *         name="idRelacion",
 *         in="query",
 *         required=true,
 *         description="Nombre del KPM a añadir.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Elimina una relación de reporter.")
 * )
 */
$app->post('/api/deleteRelacionReporter', function (Request $request, Response $response) {
	$db = new KPMs("octopus_kpms");
	$parametros =  $request->getParsedBody();
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Reporter de KPM eliminado correctamente.";
	$db->deleteRelacionReporter($parametros["idRelacion"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/eliminarKpmFormulario",
 *     tags={"Kpms"},
 *     summary="Añade un KPM al formulario.",
 *     @OA\Parameter(
 *         name="nombre",
 *         in="query",
 *         required=true,
 *         description="Nombre del KPM a añadir.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Añade un KPM al formulario.")
 * )
 */
$app->post('/api/eliminarKpmFormulario', function (Request $request, Response $response) {
	$db = new activos("octopus_kpms");
	$parametros =  $request->getQueryParams();
	$db->editarKPMFormulario($parametros, 0);
	$response->getBody()->write(json_encode("KPM añadido"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getPreguntasByUSF",
 *     tags={"Normativa"},
 *     summary="Devuelve las preguntas asignadas a un USF.",
 *     @OA\Response(response="200", description="Devuelve las preguntas asignadas a un USF.")
 * )
 */
$app->get('/api/getPreguntasByUSF', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Normativas(DB_NEW);
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Preguntas asignadas a un USF.";
	if (!isset($parametros["idUSF"])) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No has asignado un id de USF.";
	} else {
		$preguntas = $db->getPreguntasByUSF($parametros["idUSF"]);
		$preguntasNoRelacionadas = $db->getPreguntasNotUSF($parametros["idUSF"]);
		$response_data["preguntas"] = $preguntas;
		$response_data["preguntasNoRelacionadas"] = $preguntasNoRelacionadas;
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Get(
 *     path="/api/getReportersKPMs",
 *     tags={"Kpms"},
 *     summary="Devuelve cierta información calculada por 11Cert de algunos Kpms dado un activo.",
 *     @OA\Response(response="200", description="Devuelve cierta información calculada por 11Cert de algunos Kpms dado un activo.")
 * )
 */
$app->get('/api/getReportersKPMs', function (Request $request, Response $response) {
	$db_kpms = new KPMs(DB_KPMS);
	$db_users = new Usuarios(DB_USER);
	$token = $request->getAttribute(TOKEN);
	$user = $db_users->getUser($token['data']);
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "KPM eliminado correctamente.";
	$additional_access = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
	$response_data["Admin"] = $additional_access;
	$reporters = $db_kpms->getReportersKPMs($additional_access, $user[0]["id"]);
	$response_data["reporters"] = $reporters;
	$response->getBody()->write(json_encode($response_data));

	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/getMetricasKpms",
 *     tags={"Kpms"},
 *     summary="Devuelve cierta información calculada por 11Cert de algunos Kpms dado un activo.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Areas", type="object", description="Array con todas las áreas de las que queremos obtener las métricas."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Devuelve cierta información calculada por 11Cert de algunos Kpms dado un activo.")
 * )
 */
$app->post('/api/getMetricasKpms', function (Request $request, Response $response) {
	$db = new Activos(DB_SERV);
	$areas = $request->getParsedBody()["areas"];
	$metricas = obtenerMetricasAreas($db, $areas);

	$response_data = array("metricas" => $metricas);
	$response->getBody()->write(json_encode($response_data));

	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/dismissPrismaAlert",
 *     tags={"PrismaCloud"},
 *     summary="Descarta una alerta de Prisma.",
 *     @OA\RequestBody(
 *         @OA\MediaType(
 *             mediaType="application/json",
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="La alerta de Prisma ha sido descartada."
 *     )
 * )
 */
$app->post('/api/dismissPrismaAlert', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$data = $request->getParsedBody();
	if ($data === null || !isset($data["alerts"])) {
		$response->getBody()->write(json_encode(["error" => "No se ha proporcionado la alerta a descartar"]));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	$prisma->dismissPrismaAlert($data["alerts"], $data["comment"]);
	$response->getBody()->write(json_encode("Se han hecho dismiss de las alertas."));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/reopenPrismaAlert",
 *     tags={"PrismaCloud"},
 *     summary="Reabre una alerta de Prisma.",
 *     @OA\RequestBody(
 *         @OA\MediaType(
 *             mediaType="application/json",
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="La alerta de Prisma ha sido reabierta."
 *     )
 * )
 */
$app->post('/api/reopenPrismaAlert', function (Request $request, Response $response) {
	$prisma = new PrismaCloudAPI();
	$data = $request->getParsedBody();
	if ($data === null || !isset($data["alerts"])) {
		$response->getBody()->write(json_encode(["error" => "No se ha proporcionado la alerta a reabrir"]));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}

	$alerts = $prisma->reopenPrismaAlert($data["alerts"]);
	$resultadobia["alerts"] = json_decode($alerts);
	$response->getBody()->write(json_encode($resultadobia));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/assignPrismaAlertToReview",
 *     summary="Assign Prisma Alert to Review",
 *     description="Assigns a Prisma alert to a review.",
 *     tags={"Prisma"},
 *     @OA\RequestBody(
 *         @OA\MediaType(
 *             mediaType="application/json",
 *             @OA\Schema(
 *                 @OA\Property(
 *                     property="alerts",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Array of alert IDs to assign"
 *                 ),
 *                 @OA\Property(
 *                     property="reviews",
 *                     type="integer",
 *                     description="ID of the review to assign the alerts to"
 *                 ),
 *                 example={"alerts": {1, 2, 3}, "reviews": 123}
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation"
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Bad request"
 *     )
 * )
 */
$app->post('/api/assignPrismaAlertToReview', function (Request $request, Response $response) {
	$params = $request->getParsedBody();
	try {
		if (!isset($params["alerts"]) || !isset($params["idRevision"]) || empty($params["alerts"]) || empty($params["idRevision"]) || !is_array($params["alerts"]) || !is_numeric($params["idRevision"])) {
			throw new RouteException("Faltan parámetros requeridos: 'alerts' o 'idRevision' o los tipos no son los apropiados.");
		}
		$idRevision = $params["idRevision"];
		$db_revision = new Revision(DB_SERV);
		$revision = $db_revision->obtainRevisionFromId($idRevision);
		if (!isset($revision[0])  &&  !is_array($revision)) {
			throw new RouteException("Revisión no encontrada o no existe.");
		}

		if (!isset($revision[0]) || empty($revision[0]['cloudId'])) {
			throw new RouteException("Revisión no encontrada o cloudId ausente.");
		}
		$revision = $revision[0];
		$cloudId = $revision['cloudId'];
		$reportedCheck = $db_revision->checkRevisionHasReportedAlerts($revision['id']);

		if (isset($reportedCheck[0]) && $reportedCheck[0]["total"] > 0) {
			throw new RouteException("La revisión ya tiene alertas reportadas; no se pueden añadir más.");
		}

		$alerts = $params["alerts"];
		$prisma = new PrismaCloudAPI();
		$saws = new Saws();

		$infoAlerts = $prisma->getPrismaAlertInfoV2($alerts, true);
		$infoAlerts = json_decode($infoAlerts, true);
		if (!isset($infoAlerts["items"])) {
			throw new RouteException("Error al obtener las alertas de Prisma");
		}

		$results = [];
		foreach ($alerts as $alert) {
			$index = array_search($alert, array_column($infoAlerts["items"], "id"));

			if ($index === false) {
				array_push($results, ["$alert" => "Alerta no encontrada: $alert"]);
				continue;
			}


			if ($cloudId !== $infoAlerts["items"][$index]["resource"]["accountId"]) {
				array_push($results, ["$alert" => "El cloudId de la alerta $alert no coincide con el de la revisión."]);
				continue;
			}

			if ($infoAlerts["items"][$index]["status"] !== "open") {
				array_push($results, ["$alert" => "La alerta $alert no está abierta."]);
				continue;
			}
			$policyUUID = $infoAlerts["items"][$index]["policy"]["policyId"];
			$resourceId = $infoAlerts["items"][$index]["resource"]["id"];
			$resourceName = $infoAlerts["items"][$index]["resource"]["name"];

			$policyId = $saws->getPolicyIdByUUID($infoAlerts["items"][$index]["policy"]);

			if (!$policyId) {
				array_push($results, ["$alert" => "No se encontró policyId para el UUID: $policyUUID"]);
				continue;
			}

			$db_revision->assignPrismaAlertToReview($alert, $idRevision, $policyId, $resourceId, $resourceName);
			$results[] = ["$alert" => "Asignada correctamente"];
		}

		$response->getBody()->write(json_encode(["error" => false, "message" => "Alertas asignadas correctamente", "details" => $results]));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(200);
	} catch (Exception $e) {
		$response->getBody()->write(json_encode(["error" => true, "message" => $e->getMessage()]));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(400);
	}
});

/**
 * @OA\Post(
 *     path="/api/unassignPrismaAlertToReview",
 *     tags={"Prisma / EAS"},
 *     summary="Unassign Prisma Alert to Review",
 *     description="Unassigns a Prisma alert to a review.",
 *     @OA\RequestBody(
 * 	   @OA\MediaType(
 * 		   mediaType="application/json",
 * 		   @OA\Schema(
 * 			   @OA\Property(
 * 				   property="alerts",
 * 				   type="array",
 * 				   @OA\Items(type="varchar"),
 * 				   description="Array of alert IDs to unassign"
 * 			   ),
 * 			   @OA\Property(
 * 				   property="idRevision",
 * 				   type="integer",
 * 				   description="ID of the review to unassign the alerts from"
 * 			   ),
 * 			   example={"alerts": {P-1, P-2, P-3}, "idRevision": 123}
 * 		   )
 * 	   )
 *    ),
 *   @OA\Response(
 * 	   response=200,
 * 	   description="Successful operation"
 *   ),
 *  @OA\Response(
 * 	   response=400,
 * 	   description="Bad request"
 *  )
 */
$app->post('/api/unassignPrismaAlertToReview', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();

	if (!isset($parametros['alerts']) || !isset($parametros['revisionId'])) {
		$response->getBody()->write(json_encode([
			ERROR => true,
			MESSAGE => 'Parámetros incompletos: alerts y revisionId son obligatorios.'
		]));
		return $response->withStatus(400);
	}

	$alerts = $parametros['alerts'];
	$revisionId = $parametros['revisionId'];
	$db = new Revision("octopus_serv");
	try {
		foreach ($alerts as $alertId) {
			$db->unassignPrismaAlertToReview($alertId, $revisionId);
		}

		$response->getBody()->write(json_encode([
			ERROR => false,
			MESSAGE => 'Alertas ' . implode(', ', $alerts) . ' desasignadas correctamente.'
		]));
		return $response->withStatus(200);
	} catch (Exception $e) {
		$response->getBody()->write(json_encode([
			ERROR => true,
			MESSAGE => 'Error al intentar desasignar las alertas.' . implode(', ', $alerts)
		]));
		return $response->withStatus(500);
	}
});

/**
 * @OA\Post(
 *     path="/api/comprobarCampos",
 *     tags={"Evaluación / EVS"},
 *     summary="Comprueba que los campos de creación de una issue son válidos.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Resumen", type="string", description="Resumen de la issue."),
 *             @OA\Property(property="Prioridad", type="string", description="Prioridad de la issue."),
 *             @OA\Property(property="Metodologia", type="string", description="Metodología de la issue."),
 *             @OA\Property(property="Definicion", type="string", description="Definición de la issue."),
 *             @OA\Property(property="AnalysisType", type="string", description="Tipo de análisis de la issue."),
 *             @OA\Property(property="Impacto", type="string", description="Impacto de la issue."),
 *             @OA\Property(property="ProbExplotacion", type="string", description="Probabilidad de explotación de la issue."),
 *             @OA\Property(property="StatusVuln", type="string", description="Estado de la vulnerabilidad de la issue."),
 *             @OA\Property(property="Producto", type="string", description="Activo de tipo producto al que se realiza pentest."),
 *             @OA\Property(property="URL", type="string", description="URLs extra de la issue."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Comprueba que los campos de creación de una issue son válidos.")
 * )
 */
$app->post('/api/comprobarCampos', function (Request $request, Response $response) {
	$jira = new JIRA();
	$request->getUploadedFiles();
	$parametros = $request->getParsedBody();
	$response_data = $jira->compObligatorio(($parametros));
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Post(
 *     path="/api/comprobarPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Comprueba que los valores introducidos en un pentest sean válidos.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Nombre", type="string", description="Nombre del pentest."),
 *             @OA\Property(property="Responsable", type="string", description="Responsable del pentest"),
 *             @OA\Property(property="Pentest", type="string", description="Pentest al que vamos a enlazar la issue."),
 *             @OA\Property(property="Descripcion", type="string", description="Descripción del pentest."),
 *             @OA\Property(property="Fecha_inicio", type="string", description="Fecha de inicio del pentest."),
 *             @OA\Property(property="Fecha_final", type="string", description="Fecha de finalización del pentest."),
 *             @OA\Property(property="Direccion", type="string", description="Dirección de los sistemas del pentest."),
 *             @OA\Property(property="Area", type="string", description="Área de los sistemas del pentest."),
 *             @OA\Property(property="Proyecto", type="string", description="Servicio de los sistemas del pentest."),
 *             @OA\Property(property="ResponsableProy", type="string", description="Responsable del proyecto."),
 *             @OA\Property(property="AreaServ", type="string", description="Área de telefónica al que se le realiza el pentest."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Comprueba que los valores introducidos en un pentest sean válidos.")
 * )
 */
$app->post('/api/comprobarPentest', function (Request $request, Response $response) {
	$jira = new JIRA();
	$parametros = $request->getParsedBody();
	$response_data = $jira->comprobarPentest($parametros);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/crearPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Crea un pentest.",
 *     @OA\Parameter(
 *         name="tipo",
 *         in="query",
 *         required=true,
 *         description="Tipo del pentest a crear.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Nombre", type="string", description="Nombre del pentest."),
 *             @OA\Property(property="Responsable", type="string", description="Responsable del pentest"),
 *             @OA\Property(property="Descripcion", type="string", description="Descripción del pentest."),
 *             @OA\Property(property="Fecha_inicio", type="string", description="Fecha de inicio del pentest."),
 *             @OA\Property(property="Fecha_final", type="string", description="Fecha de finalización del pentest."),
 *             @OA\Property(property="Direccion", type="string", description="Dirección de los sistemas del pentest."),
 *             @OA\Property(property="Area", type="string", description="Área de los sistemas del pentest."),
 *             @OA\Property(property="Servicio", type="string", description="Servicio de los sistemas del pentest."),
 *             @OA\Property(property="Sistema", type="string", description="Sistemas del pentest.")
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un pentest.")
 * )
 */
$app->post('/api/crearPentest', function (Request $request, Response $response) {
	$jira = new JIRA();
	$query = $request->getQueryParams();
	$parametros = $request->getParsedBody();
	$db = new Pentest(DB_SERV);
	if ($query["tipo"] == "Pynt") {
		$response_data = $db->crearPentest($parametros, "Pynt");
	} else {
		$response_data = $db->crearPentest($parametros);
	}
	$jira->activosPentest($parametros);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/subirArchivos",
 *     tags={"Evaluación / EVS"},
 *     summary="Sube archivos en una issue de Jira específica.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la issue de Jira clonada.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Sube archivos en una issue de Jira específica.")
 * )
 */
$app->post('/api/subirArchivos', function (Request $request, Response $response) {
	$jira = new JIRA();
	$files = $_FILES;
	$parametros = $request->getQueryParams();
	$response->getBody()->write(json_encode("Respuesta!"));
	foreach ($files['file']['name'] as $index => $file) {
		$file_data = array();
		$file_data['name'] = $files['file']['name'][$index];
		$file_data['tmp_name'] = $files['file']['tmp_name'][$index];
		$file_data['type'] = $files['file']['type'][$index];
		$jira->adjuntarArchivo($file_data, $parametros["key"]);
		if ($parametros["clonada"] != "Sin clonar") {
			$jira->adjuntarArchivo($file_data, $parametros["clonada"]);
		}
	}
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newIssueArquitectura",
 *     tags={"Evaluación / EAS / Jira"},
 *     summary="Crea una issue en Jira.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Id", type="string", description="ID de la alerta"),
 *             @OA\Property(property="Nombre", type="string", description="Nombre de la alerta"),
 *             @OA\Property(property="Descripcion", type="string", description="Descripcion de la alerta"),
 *             @OA\Property(property="Status", type="string", description="Status de la alerta"),
 *             @OA\Property(property="Resolucion", type="string", description="Resolucion de la alerta"),
 *             @OA\Property(property="Severity", type="string", description="Severidad de la alerta"),
 *             @OA\Property(property="Fecha", type="string", description="Fecha de la alerta"),
 *             @OA\Property(property="Proyecto", type="string", description="Proyecto en el que se va a clonar la alerta"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una issue por cada alerta indicada.")
 * )
 */
$app->post('/api/newIssueArquitectura', function (Request $request, Response $response) {
	$jira       = new JIRA();
	$parametros = $request->getParsedBody();
	$db         = new Revision(DB_SERV);

	$revisionData = $db->getRevisionStatus($parametros["revisionId"]);
	if (isset($revisionData[0])) {
		if ($revisionData[0]["status"] == 1) {
			$response_data = [
				ERROR   => true,
				MESSAGE => "No se puede hacer un reporte en una revisión aún abierta."
			];
			$response->getBody()->write(json_encode($response_data));
			return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
		}
		if ($revisionData[0]["status"] == 2) {
			$reported = $db->checkRevisionHasReportedAlerts($parametros["revisionId"]);
			if (isset($reported[0]) && $reported[0]["total"] > 0) {
				$response_data = [
					ERROR   => true,
					MESSAGE => "Esta revisión tiene alertas reportadas previamente y no acepta más. Cree una nueva revisión."
				];
				$response->getBody()->write(json_encode($response_data));
				return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(400);
			}
		} elseif ($revisionData[0]["status"] == 0) {
			$reported = $db->checkRevisionHasReportedAlerts($parametros["revisionId"]);
			if (isset($reported[0]) && $reported[0]["total"] == 0) {
				$db->cambiarStatusRevision($parametros["revisionId"], "4");
			}
		}
	}

	$idsSeleccionadas = (array) $parametros["alertasAsignadas"];
	foreach ($idsSeleccionadas as $id) {
		$checkRep = $db->checkAlertaReportada($id);
		if (isset($checkRep[0]) && $checkRep[0]['reportada'] == 1) {
			$response_data = [
				ERROR   => true,
				MESSAGE => "La alerta $id ya ha sido reportada previamente"
			];
			$response->getBody()->write(json_encode($response_data));
			return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
		}

		$checkAsig = $db->checkAlertaAsignadaARevision($id, $parametros["revisionId"]);
		if (empty($checkAsig) || $checkAsig[0]['total'] == 0) {
			$response_data = [
				ERROR   => true,
				MESSAGE => "La alerta $id no está asignada a la revisión {$parametros['revisionId']}"
			];
			$response->getBody()->write(json_encode($response_data));
			return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
		}
	}

	$revisionArr = $db->obtainRevisionFromId($parametros["revisionId"]);
	$revision    = $revisionArr[0] ?? null;
	if ($revision) {
		$parametros["Fecha"]    = $revision["fecha_final"] ?? date("Y-m-d H:i:s");
		$parametros["Status"]   = "Open";
		$parametros["Proyecto"] = $revision["proyecto"];
		$parametros["CloudId"] = $revision["cloudId"];
	}

	$allVulns = $db->obtenerVulnsRevision($parametros["revisionId"]);
	$vulns    = array_filter($allVulns, function ($v) use ($idsSeleccionadas) {
		return in_array($v['id_alert'], $idsSeleccionadas, true);
	});
	if (empty($vulns)) {
		$response_data = [
			ERROR   => true,
			MESSAGE => "No se han encontrado vulnerabilidades para las alertas seleccionadas."
		];
		$response->getBody()->write(json_encode($response_data));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
	}

	$vulnsByPolicy = [];
	foreach ($vulns as $v) {
		$vulnsByPolicy[$v['id_policy']][] = $v;
	}

	$issuesReport = [];
	foreach ($vulnsByPolicy as $policyId => $group) {
		$first                     = $group[0];
		$parametros["Nombre"]      = $first["name"];
		$parametros["Descripcion"] = $first["description"];
		$parametros["Severity"]    = $first["severity"];
		$parametros["Resolucion"]  = $first["recommendation"];

		$alertIds       = array_column($group, 'id_alert');
		$alertIDsString = implode(', ', $alertIds);

		$sawInfos = [];
		foreach ($alertIds as $aid) {
			$info = $db->getSawAlert($aid, $parametros["revisionId"]);
			if (!empty($info)) {
				$sawInfos[] = $info;
			}
		}
		if (empty($sawInfos)) {
			// Ir almacenando todas las policies que no tienen SAW en un array
			$policiesWithoutSaw[] = $policyId;
			continue;
		}

		$respJira = $jira->crearIssueArquitectura(
			$parametros,
			$sawInfos,
			$alertIDsString
		);

		foreach ($alertIds as $aid) {
			$db->setAlertaReportada($aid);
			$db->insertVulnIdByRevision(
				$parametros["revisionId"],
				[$aid],
				$respJira["Execution"]
			);
		}

		$issuesReport[] = $respJira;
	}

	$executions = array_filter(
		array_map(fn($r) => $r['Execution'] ?? null, $issuesReport),
		fn($e) => $e !== null
	);

	$response_data = [
		ERROR       => false,
		'Executions' => array_values($executions),
	];

	if (!empty($policiesWithoutSaw)) {
		$response_data['MissingPolicies'] = $policiesWithoutSaw;
		$response_data['Message'] = 'Se han reportado correctamente '
			. count($executions)
			. ' alertas. No se encontraron SAW para las políticas: '
			. implode(', ', $policiesWithoutSaw);
	} else {
		$response_data['Message'] = 'Todas las alertas se han reportado correctamente.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newIssue",
 *     tags={"Evaluación / EAS / Jira"},
 *     summary="Crea una issue en Jira.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Resumen", type="string", description="Resumen de la issue."),
 *             @OA\Property(property="Prioridad", type="string", description="Prioridad de la issue."),
 *             @OA\Property(property="AreaServicio", type="string", description="Área/Servicio de la issue."),
 *             @OA\Property(property="Metodologia", type="string", description="Metodología de la issue."),
 *             @OA\Property(property="Definicion", type="string", description="Definición de la issue."),
 *             @OA\Property(property="AnalysisType", type="string", description="Tipo de análisis de la issue."),
 *             @OA\Property(property="Impacto", type="string", description="Impacto de la issue."),
 *             @OA\Property(property="ProbExplotacion", type="string", description="Probabilidad de explotación de la issue."),
 *             @OA\Property(property="StatusVuln", type="string", description="Estado de la vulnerabilidad de la issue."),
 *             @OA\Property(property="URL", type="string", description="URLs extra de la issue."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una issue por cada alerta indicada.")
 * )
 */
$app->post('/api/newIssue', function (Request $request, Response $response) {
	$jira = new JIRA();
	$files = $_FILES;
	$parametros = $request->getParsedBody();
	$response_data = $jira->crearIssue($parametros);
	$db = new Pentest(DB_SERV);
	if ($files['file']['name'][0] != "") {
		foreach ($files['file']['name'] as $index => $file) {
			$file_data = array();
			$file_data['name'] = $files['file']['name'][$index];
			$file_data['tmp_name'] = $files['file']['tmp_name'][$index];
			$file_data['type'] = $files['file']['type'][$index];
			$jira->adjuntarArchivo($file_data, $response_data["Execution"]);
		}
	}
	$pentestId = $db->obtainPentestID($parametros["pentest"]);
	$pentest = $db->obtainPentestFromId($pentestId[0]["id"]);

	if ($pentest[0]["tipo"] == "Pynt" && $pentest[0]["status"] == 7) {
		$db->cambiarStatusPentest($pentestId[0]["id"], "6");
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/createIncident",
 *     tags={"Jira"},
 *     summary="Crea una incidencia en Jira.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 * 		   	   @OA\Property(property="summary", type="string", description="Resumen de la incidencia."),
 * 		   	   @OA\Property(property="description", type="string", description="Descripción de la incidencia."),
 *         )
 *    ),
 *    @OA\Response(response="200", description="Crea una incidencia en Jira.")
 * )
 */
$app->post('/api/createIncident', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$jira = new JIRA();
	$files = $_FILES;
	$parametros = $request->getParsedBody();

	$db = new Usuarios(DB_USER);
	$usuario = $db->getUser($token['data']);

	try {
		if (isset($usuario[0]['id'])) {
			$parametros['user_mail'] = $usuario[0]['email'];
			$response_data = $jira->createIncident($parametros);

			if (isset($files['issueAttachment']) && !empty($files['issueAttachment']['name'])) {
				$jira->adjuntarArchivo($files['issueAttachment'], $response_data["Execution"]);
			}
		}
	} catch (Exception $e) {
		$response_data = [
			ERROR => true,
			MESSAGE => $e->getMessage()
		];
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api//eliminarRelacionActivo",
 *     tags={"Activos"},
 *     summary="Elimina la relación de dos activos.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Padre", type="string", description="Padre de la relación."),
 *             @OA\Property(property="Hijo", type="string", description="Hijo de la relación."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Elimina la relación de dos activos.")
 * )
 */
$app->post('/api/eliminarRelacionActivo', function (Request $request, Response $response) {
	$response_data["Error"] = false;
	$response_data["msg"] = "Relación eliminada correctamente";
	$parametros = $request->getParsedBody();
	$db = new activos("octopus_serv");

	$db->deleteRelacion($parametros["hijo"], $parametros["padre"]);

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Edita la información de un determinado pentest.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Fecha_inicio", type="string", description="Fecha de inicio del pentest."),
 *             @OA\Property(property="Fecha_final", type="string", description="Fecha de finalización del pentest."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita la información de un determinado pentest.")
 * )
 */
$app->post('/api/editPentest', function (Request $request, Response $response) {
	$response_data["Error"] = false;
	$response_data["msg"] = "Pentest modificado correctamente";
	$parametros = $request->getParsedBody();
	$db = new Pentest("octopus_serv");

	if ($parametros["Fecha_inicio"] != "" && $parametros["Fecha_final"] == "") {
		$db->editDateStart($parametros['id'], $parametros["Fecha_inicio"]);
	} elseif ($parametros["Fecha_inicio"] == "" && $parametros["Fecha_final"] != "") {
		$db->editDateEnd($parametros['id'], $parametros["Fecha_final"]);
	} elseif ($parametros["Fecha_inicio"] != "" && $parametros["Fecha_final"] != "") {
		$db->editDateStart($parametros['id'], $parametros["Fecha_inicio"]);
		$db->editDateEnd($parametros['id'], $parametros["Fecha_final"]);
	}

	if (isset($parametros["resp_pentest"]) && $parametros["resp_pentest"] != "") {
		$db->editPentester($parametros['id'], $parametros["resp_pentest"]);
	}

	if (isset($parametros["Producto"]) && $parametros["Producto"] != "Ninguno") {
		$worked = editActivosPentest($parametros["id"], $parametros["Producto"]);
		if (!$worked) {
			$response_data["Error"] = true;
			$response_data["msg"] = "Error. Este pentest ya ha sido asignado y por lo tanto no puedes cambiar el producto/servicio. Contacta con consultoría de 11Cert.";
		}
	}

	if (isset($parametros["Nombre"]) && $parametros["Nombre"] != "") {
		if (isset($db->obtainPentestID($parametros["Nombre"])[0]["id"])) {
			$response_data["Error"] = true;
			$response_data["msg"] = "Error. Ya existe un pentest con ese mismo nombre.";
		} else {
			$db->editPentestName($parametros["Nombre"], $parametros["id"]);
		}
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/modificarClonada",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Modifica una issue de Jira clonada de otra issue de Jira.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Responsable", type="string", description="Responsable del proyecto donde se crea la clonada."),
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="Responsable",
 *         in="query",
 *         required=true,
 *         description="Nombre del responsable de la issue.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Modifica una issue de Jira clonada de otra issue de Jira.")
 * )
 */
$app->post('/api/modificarClonada', function (Request $request, Response $response) {
	$jira = new JIRA();
	$files = $_FILES;
	$parametros = $request->getParsedBody();
	$key = $request->getQueryParams();
	$key = $key["clone"];
	$response_data = $jira->updateClone($key, $parametros);
	if ($files['file']['name'][0] != "") {
		foreach ($files['file']['name'] as $index => $file) {
			$file_data = array();
			$file_data['name'] = $files['file']['name'][$index];
			$file_data['tmp_name'] = $files['file']['tmp_name'][$index];
			$file_data['type'] = $files['file']['type'][$index];
			$jira->adjuntarArchivo($file_data, $key);
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editIssue",
 *     tags={"Evaluación / EVS / Jira"},
 *     summary="Edita una issue de Jira.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Resumen", type="string", description="Resumen de la issue."),
 *             @OA\Property(property="Prioridad", type="string", description="Prioridad de la issue."),
 *             @OA\Property(property="metodologia", type="string", description="Metodología de la issue."),
 *             @OA\Property(property="Definicion", type="string", description="Definición de la issue."),
 *             @OA\Property(property="Informador", type="string", description="Informador de la issue."),
 *             @OA\Property(property="radio", type="string", description="Tipo de vulnerabilidad seleccionada."),
 *             @OA\Property(property="VulImpact", type="string", description="Impacto de la issue."),
 *             @OA\Property(property="ExpProb", type="string", description="Probabilidad de explotación de la issue."),
 *             @OA\Property(property="VulnStatus", type="string", description="Estado de la vulnerabilidad de la issue."),
 *             @OA\Property(property="URL", type="string", description="URLs extra de la issue."),
 *             @OA\Property(property="AreaServ", type="string", description="Área/Servicio de la issue."),
 *             @OA\Property(property="Descrip", type="string", description="Descripción de la issue."),
 *         )
 *     ),
 *     @OA\Parameter(
 *         name="key",
 *         in="query",
 *         required=true,
 *         description="ID de la issue.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Edita una issue de Jira.")
 * )
 */
$app->post('/api/editIssue', function (Request $request, Response $response) {
	$jira = new JIRA();
	$db = new Pentest(DB_SERV);
	$parametros = $request->getParsedBody();
	$key = $request->getQueryParams();
	$key = $key["key"];
	$issue = $jira->obtenerIssue($key);
	$clon = $issue["issues"][0]["fields"]["issuelinks"][0]["inwardIssue"]["key"];
	$response_data = $jira->updateIssue($key, $parametros);
	if (isset($clon)) {
		$jira->updateIssueExterna($clon, $parametros);
	}
	$db->eliminarIssuePentest($key);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/cambiarStatusPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Reabre un pentest.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="status",
 *         in="query",
 *         required=true,
 *         description="Nuevo status del pentest.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Cambia el estado de un pentest.")
 * )
 */
$app->post('/api/reabrirPentest', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$db = new Pentest("octopus_serv");
	$db->cambiarStatusPentest($parametros["id"], $parametros["status"]);
	$response->getBody()->write(json_encode("Pentest reabierto"));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editPersonasActivo",
 *     tags={"Activos"},
 *     summary="Edita las personas responsables de un activo.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", description="id del activo."),
 *             @OA\Property(property="product_owner", type="string"),
 *             @OA\Property(property="r_seguridad", type="string"),
 *             @OA\Property(property="r_config_puesto_trabajo", type="string"),
 *             @OA\Property(property="r_operaciones", type="string"),
 *             @OA\Property(property="r_desarrollo", type="string"),
 *             @OA\Property(property="r_legal", type="string"),
 *             @OA\Property(property="r_rrhh", type="string"),
 *             @OA\Property(property="r_kpms", type="string"),
 *             @OA\Property(property="consultor_ciso", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita las personas responsables de un activo.")
 * )
 */
$app->post('/api/editPersonasActivo', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_SERV);
	$response_data = [];
	if (isset($parametros["id"])) {
		$resultado = $db->editPersonasActivo($parametros["id"], $token['data'], $parametros);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'No se encontró el activo que se quiere editar.';
	}
	if ($resultado == 'NOPROPIETARIO') {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'No eres el propietario de este activo y no puedes editarlo.';
	} else {
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = 'Personas del activo editado correctamente.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/realizarEvaluacion",
 *     tags={"Evaluación / EVS / SDLC"},
 *     summary="Realiza una evaluación de los sistemas de una prueba.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID de la prueba que se quiere realizar la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Pentest Cerrado"),
 *     @OA\Response(response="404", description="No existe evaluación para el activo y aun no se puede cerrar el pentest. Contacta con el responsable.")
 * )
 */
$app->post('/api/realizarEvaluacion', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data[ERROR] = false;
	$db = new Pentest("octopus_serv");
	$jira = new JIRA();
	$error = $jira->gestionarSistemas($parametros["id"]);
	if ($error != "No evaluations") {
		$pentest = $db->obtainPentestFromId($parametros["id"]);
		if ($pentest[0]["status"] == 3 || $pentest[0]["status"] == 4) {
			$db->cambiarStatusPentest($parametros["id"], "0");
		}
		if ($pentest[0]["status"] == 6) {
			$db->cambiarStatusPentest($parametros["id"], "7");
		}
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "Pentest Cerrado.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No existe evaluación para el activo y aun no se puede cerrar el pentest. Contacta con el responsable.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(404);
	}
});

$app->post('/api/realizarEvaluacionRevision', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$response_data[ERROR] = false;
	$db = new Revision("octopus_serv");
	$jira = new JIRA();
	$error = $jira->gestionarSistemasRevision($parametros["id"]);
	if ($error != "No evaluations") {
		$revision = $db->obtainRevisionFromId($parametros["id"]);
		if ($revision[0]["status"] == 3 || $revision[0]["status"] == 4) {
			$db->cambiarStatusRevision($parametros["id"], "0");
		}
		if ($revision[0]["status"] == 6) {
			$db->cambiarStatusRevision($parametros["id"], "7");
		}
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "Revision Cerrada.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No existe evaluación para el activo y aun no se puede cerrar la revision. Contacta con el responsable.";
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(404);
	}
});
/**
 * @OA\Post(
 *     path="/api/importActivos",
 *     tags={"Activos"},
 *     summary="Crea un activo según una plantilla pasada en el body.",
 *     @OA\Response(response="200", description="Crea un activo según una plantilla pasada en el body.")
 * )
 */
$app->post('/api/importActivos', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$files = $request->getUploadedFiles();
	$response_data[ERROR] = true;
	$response_data[MESSAGE] = "No se ha cargado ningún archivo válido.";

	if (isset($files['file'])) {
		$file = $files['file'];
		$file_type = $file->getClientMediaType();

		$allowedMimeTypes = [
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'application/vnd.ms-excel'
		];

		if (in_array($file_type, $allowedMimeTypes)) {

			$randomFileName = bin2hex(random_bytes(4)) . ".xlsx";

			$tempFilePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $randomFileName;

			$file->moveTo($tempFilePath);

			try {
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
				$spreadsheet = $reader->load($tempFilePath);

				$sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

				$respuesta = crearActivosExcel($sheetData, $token['data']);
				$response_data[ERROR] = $respuesta[ERROR];
				$response_data[MESSAGE] = $respuesta[MESSAGE];
			} catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
				$response_data[MESSAGE] = "Error al procesar el archivo Excel. El archivo puede estar dañado o no ser un archivo Excel válido.";
			}

			if (file_exists($tempFilePath)) {
				unlink($tempFilePath);
			}
		} else {
			$response_data[MESSAGE] = "El archivo debe ser un Excel válido (.xls, .xlsx).";
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/importEval",
 *     tags={"Evaluaciones"},
 *     summary="Crea una evaluación según una plantilla pasada en el body.",
 *     @OA\Parameter(
 *         name="id",
 *         in="query",
 *         required=true,
 *         description="ID del activo.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="normativa",
 *         in="query",
 *         required=true,
 *         description="Id de la normativa.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Crea una evaluación según una plantilla pasada en el body.")
 * )
 */
$app->post('/api/importEval', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();

	if (isset($parametros['id']) && !empty($parametros['id']) && is_numeric($parametros['id']) && isset($parametros[NORMATIVA])) {
		$response_data[ERROR] = false;
		$activo_id = $parametros['id'];
		$db = new DbOperations(DB_SERV);
		$evaluaciones = $db->getFechaEvaluaciones($activo_id, true);
		if (count($evaluaciones) == 0) {
			$files = $request->getUploadedFiles();
			if (isset($files['file']) && "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" === $files['file']->getClientmediaType()) {
				$file = $files['file'];
				$file_name = $file->getClientFilename();
				$file->moveTo("./" . UPLOADS . "/$file_name");
				$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
				$spreadsheet = $reader->load("." . DIRECTORY_SEPARATOR . UPLOADS . DIRECTORY_SEPARATOR  . $file_name);
				$sheetData = $spreadsheet->getActiveSheet();
				$sheetData = $sheetData->toArray(null, false, true, true);
				unlink("." . DIRECTORY_SEPARATOR . UPLOADS . DIRECTORY_SEPARATOR  . $file_name);
				if (!$response_data[ERROR]) {
					$db = new DbOperations(DB_SERV);
					$resultadopregunta = array();
					$resultadocoment = array();
					if ($parametros['normativa'] == '3ps') {
						if ($sheetData[8]["D"] != "Código") {
							$response_data[ERROR] = true;
							$response_data[MESSAGE] = "En la columna 'D' debe ir el 'Código'.";
						}
						if ($sheetData[8]["I"] != "Medida") {
							$response_data[ERROR] = true;
							$response_data[MESSAGE] = "En la columna 'I' debe ir la 'Medida'.";
						}
						if ($sheetData[8]["J"] != "Cumplimiento") {
							$response_data[ERROR] = true;
							$response_data[MESSAGE] = "En la columna 'J' debe ir el 'Cumplimiento'.";
						}
						if ($sheetData[8]["K"] != "Comentarios") {
							$response_data[ERROR] = true;
							$response_data[MESSAGE] = "En la columna 'K' debe ir 'Comentarios'.";
						}
						if ($response_data[ERROR]) {
							$response->getBody()->write(json_encode($response_data));
							return $response
								->withHeader(CONTENT_TYPE, JSON)
								->withStatus(200);
						}
						$resultadopregunta['3ps'] = 1;
						for ($i = 0; $i < 8; $i++) {
							array_shift($sheetData);
						}
					} else {
						array_shift($sheetData);
					}
					foreach ($sheetData as $index => $fila) {
						if ($parametros['normativa'] == '3ps') {
							if (empty($fila['A']) && empty($fila['B']) && empty($fila['C']) && empty($fila['D']) && empty($fila['E']) && empty($fila['F']) && empty($fila['G']) && empty($fila['H']) && empty($fila['I']) && empty($fila['J']) && empty($fila['K'])) {
								break;
							}
							if (isset($fila['D']) && $fila['D'] !== null && isset($fila['I']) && $fila['I'] == "Obligatorio") {
								$id = $db->getId3PSbyCod($fila['D']);
								if (isset($fila['J']) && $fila['J'] === "Cumple") {
									$fila['J'] = "1";
								} else {
									$fila['J'] = "0";
								}
								if (isset($id[0])) {
									$resultadopregunta[$id[0]['id']] = $fila['J'];
									if (!isset($fila['K']) || $fila['K'] == null) {
										$fila['K'] = "";
									} elseif ($fila['K'][0] == "=") {
										$posicion = $index + 8;
										$response_data[ERROR] = true;
										$response_data[MESSAGE] = "Parece que hay una fórmula en la fila $posicion.";
										break;
									}
									$resultadocoment[$id[0]['id']] = $fila['K'];
								} else {
									$codigo = $fila['D'];
									$response_data[ERROR] = true;
									$response_data[MESSAGE] = "El código $codigo no se encuentra en la base de datos.";
									break;
								}
							} elseif (!isset($fila['D']) || $fila['D'] == null) {
								$response_data[ERROR] = true;
								$response_data[MESSAGE] = "El campo del código no puede estar vacío.";
								break;
							} elseif (!isset($fila['I']) || ($fila['I'] != "Recomendable" && $fila['I'] != "Opcional")) {
								$response_data[ERROR] = true;
								$medida = $fila['I'];
								$response_data[MESSAGE] = "El Valor '$medida' en el campo medida no es valido.";
								break;
							}
						} else {
							if (isset($fila['F'])) {
								if (strtolower($fila['F']) === "si") {
									$fila['F'] = "1";
								} elseif (strtolower($fila['F']) === "no") {
									$fila['F'] = "0";
								} elseif ($fila['F'] === "") {
									// Si la columna 'F' está vacía, no hacer nada y continuar con la siguiente fila
									continue;
								} else {
									$response_data[ERROR] = true;
									$filaindex = $index + 2;
									$response_data[MESSAGE] = "El valor de la fila $filaindex - columna 'F' no es el correcto, puede que haya introducido una formula en su lugar y la herramienta no acepta formulas en esta columna, revíselo y vuelva a subir el cuestionario.";
									break;
								}

								if (isset($fila['A'])) {
									$resultadopregunta[$fila['A']] = $fila['F'];
								} else {
									$response_data[ERROR] = true;
									$filaindex = $index + 2;
									$response_data[MESSAGE] = "El campo 'A' en la fila $filaindex no puede estar vacío.";
									break;
								}

								if (!isset($fila['G']) || $fila['G'] == null) {
									$fila['G'] = "";
								}
								$resultadocoment[$fila['A']] = $fila['G'];
							}
						}
					}
					if (!$response_data[ERROR]) {
						$db->setMetaValue($activo_id, $resultadopregunta, PREGUNTAS);
						$db->setMetaValue($activo_id, $resultadocoment, 'comentarios');
						updateListPac($activo_id);
						$response_data[MESSAGE] = "Archivo importado correctamente.";
						$response_data["redirect"] = "historialservicio?id=$activo_id";
					}
				}
			} else {
				$response_data[ERROR] = true;
				$response_data[MESSAGE] = "El archivo que intentas cargar no es un xlsx.";
			}
		} else {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = "Este activo ya tiene almenos una evaluación finalizada, no se puede importar otra.";
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editRevision",
 *     tags={"Evaluación / EVS"},
 *     summary="Edita la información de una determinada revision.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Fecha_inicio", type="string", description="Fecha de inicio de la revision."),
 *             @OA\Property(property="Fecha_final", type="string", description="Fecha de finalización de la revision."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita la información de una determinada revision.")
 * )
 */
$app->post('/api/editRevision', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Revision("octopus_serv");
	if ($parametros["Fecha_inicio"] != "" && $parametros["Fecha_final"] == "") {
		$db->editDateStart($parametros['id'], $parametros["Fecha_inicio"]);
	} elseif ($parametros["Fecha_inicio"] == "" && $parametros["Fecha_final"] != "") {
		$db->editDateEnd($parametros['id'], $parametros["Fecha_final"]);
	} elseif ($parametros["Fecha_inicio"] != "" && $parametros["Fecha_final"] != "") {
		$db->editDateStart($parametros['id'], $parametros["Fecha_inicio"]);
		$db->editDateEnd($parametros['id'], $parametros["Fecha_final"]);
	}
	$response_data = "Datos devueltos correctamente";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/insertActivosPentest",
 *     tags={"Evaluación / EVS"},
 *     summary="Inserta los activos de tipo sistema que va a tener la evaluación del pentest.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="Servicio", type="string", description="Servicio padre de los sistemas."),
 *             @OA\Property(property="Sistema", type="string", description="Sistema al que va a afectar el pentest."),
 *             @OA\Property(property="SistemaX", type="string", description="Se añade un campo sistema mas con un número por cada sistema mas incluido. Ejemplo: Sistema1, Sistema2, Sistema3..."),
 *             @OA\Property(property="id", type="string", description="Id del pentest."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Asigna los sistemas que va a tener un pentest.")
 * )
 */
$app->post('/api/insertActivosPentest', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Pentest("octopus_serv");
	$arraySistemas = array();
	array_push($arraySistemas, $parametros["Sistema"]);
	$numSistema = 1;
	while (isset($parametros["Sistema" . strval($numSistema)])) {
		if (
			$parametros["Sistema" . strval($numSistema)] != "Ninguno" &&
			!in_array($parametros["Sistema" . strval($numSistema)], $arraySistemas)
		) {
			array_push($arraySistemas, $parametros["Sistema" . strval($numSistema)]);
		}
		$numSistema += 1;
	}
	$db->insertarID($parametros["id"], $arraySistemas);
	//Editamos el estado del pentest comoidentificado
	$pentest = $db->obtainPentestFromId($parametros["id"]);
	if ($pentest[0]["status"] == 2) {
		$db->cambiarStatusPentest($parametros["id"], "3");
	} elseif ($pentest[0]["status"] == 5) {
		$db->cambiarStatusPentest($parametros["id"], "6");
	}
	$response->getBody()->write(json_encode("Se han añadido los sistemas correctamente."));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Post(
 *     path="/api/ModEstadoPacSeguimiento",
 *     tags={"Evaluación / PAC"},
 *     summary="Edita solo el estado de un PAC de seguimiento.",
 *     @OA\Parameter(
 *         name="sysName",
 *         in="query",
 *         required=true,
 *         description="Nombre del sistema al que pertenece el pac.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="estado", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita solo el estado de un PAC de seguimiento.")
 * )
 */
$app->post('/api/ModEstadoPacSeguimiento', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_SERV);
	$seguimiento = $db->getSeguimientoById($parametros['id']);
	if (isset($seguimiento[0])) {
		$mod = array();
		if ($parametros["estado"] !== $seguimiento[0]["estado"]) {
			$mod["estado"] = $parametros["estado"];
			if ($parametros["estado"] == "Finalizado" || $parametros["estado"] == "Descartado") {
				$mod["fin"] = date("Y-m-d");
			} elseif ($parametros["estado"] == "Iniciado") {
				$mod["inicio"] = date("Y-m-d");
			}
		}
		$db->editPacSeguimiento($parametros['id'], $mod);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editPacSeguimiento",
 *     tags={"Evaluación / PAC"},
 *     summary="Edita los Pac de seguimiento.",
 *     @OA\Parameter(
 *         name="sysName",
 *         in="query",
 *         required=true,
 *         description="Nombre del sistema al que pertenece el pac.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="inicio", type="string"),
 *             @OA\Property(property="fin", type="string"),
 *             @OA\Property(property="estado", type="string"),
 *             @OA\Property(property="responsable", type="string"),
 *             @OA\Property(property="comentarios", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita los Pac de seguimiento.")
 * )
 */
$app->post('/api/editPacSeguimiento', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_SERV);
	$seguimiento = $db->getSeguimientoById($parametros['id']);
	if (isset($seguimiento[0])) {
		$mod = array();
		if ($parametros["inicio"] !== $seguimiento[0]["inicio"] && $parametros["inicio"] !== '') {
			$mod["inicio"] = $parametros["inicio"];
		}
		if ($parametros["fin"] !== $seguimiento[0]["fin"]  && $parametros["fin"] !== '') {
			$mod["fin"] = $parametros["fin"];
		}
		if ($parametros["estado"] !== $seguimiento[0]["estado"]) {
			$mod["estado"] = $parametros["estado"];
		}
		if ($parametros["responsable"] !== $seguimiento[0]["responsable"]) {
			$mod["responsable"] = $parametros["responsable"];
		}
		if ($parametros["comentarios"] !== $seguimiento[0]["comentarios"]) {
			$mod["comentarios"] = $parametros["comentarios"];
		}
		$db->editPacSeguimiento($parametros['id'], $mod);
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deletePacSeguimiento",
 *     tags={"Evaluación / PAC"},
 *     summary="Elimina un pac de seguimiento.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Elimina un pac de seguimiento.")
 * )
 */
$app->post('/api/deletePacSeguimiento', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_SERV);
	$seguimiento = $db->getSeguimientoById($parametros["id"]);

	if (isset($seguimiento[0])) {
		$db->delPacSeguimiento($parametros['id']);
		$response_data[MESSAGE] = 'Seguimiento borrado correctamente.';
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'No se ha localizado ningún seguimiento con ese ID.';
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deletePlan",
 *     tags={"Planes / Servicios"},
 *     summary="Eliminar un plan de servicios.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="id", type="string", description="ID del plan a eliminar.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Resultado de la eliminación del plan de servicios.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="error", type="boolean", description="Estado de error de la solicitud."),
 *             @OA\Property(property="message", type="string", description="Mensaje detallado de la respuesta.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="No autorizado. Token inválido o no presente."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Permiso denegado. Usuario con permisos de lectura o rol insuficiente."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor."
 *     )
 * )
 */
$app->post('/api/deletePlan', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$db->delPlan($parametros["id"]);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/updateEvalNe",
 *     tags={"Evaluaciones"},
 *     summary="Genera una nueva versión de evaluación incluyendo las preguntas de los sistemas no evaluados.",
 *     @OA\Parameter(
 *         name="fecha",
 *         in="query",
 *         required=true,
 *         description="Fecha de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="idVersion",
 *         in="query",
 *         required=true,
 *         description="ID de la versión de la evaluación.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Genera una nueva versión de evaluación incluyendo las preguntas de los sistemas no evaluados.")
 * )
 */
$app->post('/api/updateEvalNe', function (Request $request, Response $response) {
	$parametros = $request->getQueryParams();
	$form = $request->getParsedBody();
	$db = new DbOperations(DB_SERV);
	$eval = $db->getPreguntasEvaluacionByFecha($parametros['fecha']);
	if ($parametros['idVersion'] !== "null") {
		$eval = $db->getPreguntasversionByFecha($parametros['idVersion']);
	}
	if (isset($eval[0])) {
		$eval = json_decode($eval[0]["preguntas"], true);
	}
	$eval = $eval + $form["evaluate"];
	$db->editEval($parametros['fecha'], $parametros['idVersion'], $eval);
	$response_data[ERROR] = false;

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * @OA\Post(
 *     path="/api/editKpms",
 *     tags={"Kpms"},
 *     summary="Edita un KPM pasándole el tipo y el ID en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", description="Id del kpm"),
 *             @OA\Property(property="tipo", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita un KPM pasándole el tipo y el ID en el body.")
 * )
 */
$app->post('/api/editKpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [
		ERROR => true,
		MESSAGE => 'Usuario no identificado.'
	];
	if (isset($token["data"])) {
		$parametros = $request->getParsedBody();
		$db = new Usuarios(DB_USER);
		$user = $db->getUser($token["data"]);
		$db = new Activos(DB_KPMS);
		$response_data = $db->editkpm($parametros, $user);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteKpms",
 *     tags={"Kpms"},
 *     summary="Elimina un KPM pasándole el tipo y el ID en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", description="Id del kpm"),
 *             @OA\Property(property="tipo", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Elimina un KPM pasándole el tipo y el ID en el body.")
 * )
 */
$app->post('/api/deleteKpms', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$db = new Activos(DB_KPMS);
	$response_data = $db->delkpms($parametros);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/lockKpms",
 *     tags={"Kpms"},
 *     summary="Bloquea la edición del reporte de Kpms.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", description="Id del kpm"),
 *             @OA\Property(property="tipo", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Bloquea la edición del reporte de Kpms.")
 * )
 */
$app->post('/api/lockKpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	if (isset($token["data"])) {
		$parametros = $request->getParsedBody();
		$db = new Activos(DB_KPMS);
		$response_data = $db->lockkpms($parametros);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/unlockKpms",
 *     tags={"Kpms"},
 *     summary="Desbloquea la edición del reporte de Kpms.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string", description="Id del kpm"),
 *             @OA\Property(property="tipo", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Desbloquea la edición del reporte de Kpms.")
 * )
 */
$app->post('/api/unlockKpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	if (isset($token["data"])) {
		$parametros = $request->getParsedBody();
		$db = new Activos(DB_KPMS);
		$response_data = $db->unlockkpms($parametros);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newPlan",
 *     tags={"Planes / Servicios"},
 *     summary="Crear un nuevo plan de servicios.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="id", type="string", description="ID del nuevo plan."),
 *             @OA\Property(property="direccion", type="string", description="Dirección del plan."),
 *             @OA\Property(property="area", type="string", description="Área del plan."),
 *             @OA\Property(property="unidad", type="string", description="Unidad del plan."),
 *             @OA\Property(property="criticidad", type="string", description="Criticidad del plan."),
 *             @OA\Property(property="prioridad", type="string", description="Prioridad del plan."),
 *             @OA\Property(property="servicio", type="string", description="Servicio del plan."),
 *             @OA\Property(property="estado", type="string", description="Estado del plan."),
 *             @OA\Property(property="elevencert", type="string", description="Nivel de elevencert del plan."),
 *             @OA\Property(property="eprivacy", type="string", description="Nivel de privacidad del plan."),
 *             @OA\Property(property="jefeproyecto", type="string", description="Jefe del proyecto."),
 *             @OA\Property(property="secretoempresarial", type="string", description="Secreto empresarial."),
 *             @OA\Property(property="entorno", type="string", description="Entorno del plan."),
 *             @OA\Property(property="tenable", type="string", description="Nivel de tenable del plan."),
 *             @OA\Property(property="dome9", type="string", description="Nivel de dome9 del plan."),
 *             @OA\Property(property="usuarioacceso", type="string", description="Usuario de acceso."),
 *             @OA\Property(property="revisiones", type="string", description="Revisiones del plan."),
 *             @OA\Property(property="q1", type="string", description="Primera evaluación trimestral."),
 *             @OA\Property(property="q2", type="string", description="Segunda evaluación trimestral."),
 *             @OA\Property(property="q3", type="string", description="Tercera evaluación trimestral."),
 *             @OA\Property(property="q4", type="string", description="Cuarta evaluación trimestral.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Resultado de la creación del nuevo plan de servicios.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="error", type="boolean", description="Estado de error de la solicitud."),
 *             @OA\Property(property="message", type="string", description="Mensaje detallado de la respuesta.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="No autorizado. Token inválido o no presente."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Permiso denegado. Usuario con permisos de lectura o rol insuficiente."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor."
 *     )
 * )
 */
$app->post('/api/newPlan', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$db->newPlan($parametros);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/createPac",
 *     tags={"Evaluación / PAC"},
 *     summary="Crea un nuevo pac pasándole un activo en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="sistema", type="integer", description="ID del sistema al que se le va a crear el PAC."),
 *             @OA\Property(property="pac", type="integer", description="ID del PAC que se va a crear."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un nuevo pac pasándole un activo en el body.")
 * )
 */
$app->post('/api/createPac', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	try {
		if (!isset($parametros['sistema']) || !isset($parametros['pac']) || !is_numeric($parametros['sistema']) || !is_numeric($parametros['pac'])) {
			throw new RouteException("Parámetros 'sistema' y 'pac' son obligatorios y deben ser numéricos.");
		}

		$dbActivos = new Activos(DB_SERV);
		$padre = $dbActivos->getActivo($parametros['sistema']);
		if (!isset($padre[0]) || $padre[0]["tipo"] != 33) {
			throw new RouteException("El activo especificado no es un activo válido o no existe.");
		}
		$hijos = $dbActivos->getHijos($padre[0]["id"]);
		$dbOperations = new Activos();
		$obligatorios = $dbOperations->getClaseActivosObligatorios();
		foreach ($obligatorios as &$activo) {
			$activo["tipo_id"] = $activo["tipo"];
			unset($activo["tipo"]);
		}
		$childs = array_merge($obligatorios, $hijos);
		$preguntas = $dbOperations->getPreguntasByActivosProyecto($childs, $parametros['pac']);

		if (!count($preguntas) > 0) {
			throw new RouteException("No se han encontrado preguntas para el PAC especificado.");
		}
		$pac = $parametros['pac'];
		$lasteval = $dbActivos->getFechaEvaluaciones($padre[0]["id"], true);

		if (!isset($lasteval[0])) {
			throw new RouteException("Este activo no tiene una evaluación previa. Selecciona uno que sí la tenga para continuar.");
		}
		if ($lasteval[0]["tipo_tabla"] == "evaluaciones") {
			$eval = $dbActivos->getPreguntasEvaluacionByFecha($lasteval[0]["id"]);
			$evalid = $lasteval[0];
			$evalversionid = null;
		} else {
			$eval = $dbActivos->getPreguntasVersionByFecha($lasteval[0]["id"]);
			$evalversionid = $lasteval[0]["id"];
			$evalid = end($lasteval);
		}

		if (!isset($eval[0])) {
			throw new RouteException("No se han encontrado preguntas para la evaluación especificada.");
		}

		$eval = json_decode($eval[0]["preguntas"], true);

		foreach ($preguntas as $elemento) {
			$idPregunta = $elemento['id_preguntas'];
			$eval[$idPregunta] = "0";
		}
		$dbActivos->editEval($evalid["id"], $evalversionid, $eval, "Nuevo PAC$pac");
		$response_data[ERROR] = false;
		$response_data[MESSAGE] = "Nuevo PAC creado correctamente.";
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Error al crear el PAC: " . $e->getMessage();
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(400);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editPlan",
 *     tags={"Planes / Servicios"},
 *     summary="Editar un plan de servicios.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="id", type="string", description="ID del plan a editar."),
 *             @OA\Property(property="direccion", type="string", description="Dirección del plan."),
 *             @OA\Property(property="area", type="string", description="Área del plan."),
 *             @OA\Property(property="unidad", type="string", description="Unidad del plan."),
 *             @OA\Property(property="criticidad", type="string", description="Criticidad del plan."),
 *             @OA\Property(property="prioridad", type="string", description="Prioridad del plan."),
 *             @OA\Property(property="servicio", type="string", description="Servicio del plan."),
 *             @OA\Property(property="estado", type="string", description="Estado del plan."),
 *             @OA\Property(property="elevencert", type="string", description="Nivel de elevencert del plan."),
 *             @OA\Property(property="eprivacy", type="string", description="Nivel de privacidad del plan."),
 *             @OA\Property(property="jefeproyecto", type="string", description="Jefe del proyecto."),
 *             @OA\Property(property="secretoempresarial", type="string", description="Secreto empresarial."),
 *             @OA\Property(property="entorno", type="string", description="Entorno del plan."),
 *             @OA\Property(property="tenable", type="string", description="Nivel de tenable del plan."),
 *             @OA\Property(property="dome9", type="string", description="Nivel de dome9 del plan."),
 *             @OA\Property(property="usuarioacceso", type="string", description="Usuario de acceso."),
 *             @OA\Property(property="revisiones", type="string", description="Revisiones del plan."),
 *             @OA\Property(property="q1", type="string", description="Primera evaluación trimestral."),
 *             @OA\Property(property="q2", type="string", description="Segunda evaluación trimestral."),
 *             @OA\Property(property="q3", type="string", description="Tercera evaluación trimestral."),
 *             @OA\Property(property="q4", type="string", description="Cuarta evaluación trimestral.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Resultado de la edición del plan de servicios.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="error", type="boolean", description="Estado de error de la solicitud."),
 *             @OA\Property(property="message", type="string", description="Mensaje detallado de la respuesta.")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="No autorizado. Token inválido o no presente."
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Permiso denegado. Usuario con permisos de lectura o rol insuficiente."
 *     ),
 *     @OA\Response(
 *         response=500,
 *         description="Error interno del servidor."
 *     )
 * )
 */
$app->post('/api/editPlan', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	$response_data[ERROR] = false;
	$db = new Activos(DB_SERV);
	$db->editPlan($parametros);
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newActivo",
 *     tags={"Activos"},
 *     summary="Crea un activo.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="dependencia", type="string"),
 *             @OA\Property(property="padre_id", type="string"),
 *             @OA\Property(property="nombre", type="string"),
 *             @OA\Property(property="tipo", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un activo.")
 * )
 */
$app->post('/api/newActivo', function (Request $request, Response $response) {
	$response_data = [];
	$token = $request->getAttribute(TOKEN);
	$db = new Activos(DB_SERV);
	$parametros = $request->getParsedBody();
	if ($parametros['dependencia'] !== '') {
		$hijo = $db->getActivoByNombre($parametros['dependencia']);
		$padre = $db->getActivo($parametros['padre_id']);
		if ($hijo[0]['nombre'] != $padre[0]['nombre']) {
			$resultado = $db->newActivo($parametros[NOMBRE], $parametros['tipo'], $parametros['padre_id'], $token['data']);
			if ($parametros['dependencia'] !== '') {
				$padre = $db->getActivoByNombre($parametros[NOMBRE]);
				$db->newParentesco($hijo[0]['id'], $padre[0]['id']);
			}
			$response_data[ERROR] = false;
		} else {
			$response_data[ERROR] = true;
			$resultado = "Se está creando un activo que genera un bucle infinito y no se ha podido crear.";
		}
	} else {
		$resultado = $db->newActivo($parametros["nombre"], $parametros['tipo'], $parametros['padre_id'], $token['data']);
		$response_data = $resultado;
	}
	if (!$resultado[ERROR]) {
		createLogsNewActivo($parametros["nombre"], $token);
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $resultado["message"];
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/updateArchivados",
 *     tags={"Activos"},
 *     summary="Actualiza los activos archivados.",
 *     @OA\Response(response="200", description="Actualiza los activos archivados.")
 * )
 */
$app->post('/api/updateArchivados', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Usuarios(DB_USER);
	$user = $db->getUser($token['data']);
	if (isset($user[0]) && $user[0]["rol"] == "admin") {
		$db = new Activos(DB_SERV);
		$archivados = $db->getServiciosArchivados();
		foreach ($archivados as $archivado) {
			$hijos = $db->getHijos($archivado["id"]);
			$db->archivarActivos($hijos, 1, $token['data']);
		}
	}
	$response->getBody()->write(json_encode("Activos archivados correctamente."));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editActivo",
 *     tags={"Activos"},
 *     summary="Edita un activo pasado en el body",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="activo_id", type="string"),
 *             @OA\Property(property="descripcion", type="string"),
 *             @OA\Property(property="archivado", type="string"),
 *             @OA\Property(property="expuesto", type="string"),
 *             @OA\Property(property="externo", type="string"),
 *             @OA\Property(property="id", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita un activo pasado en el body")
 * )
 */
$app->post('/api/editActivo', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$parametros = $request->getParsedBody();
	try {
		$db = new Activos(DB_SERV);
		if (isset($parametros["activo_id"])) {
			$descripcion = '';
			if (isset($parametros["descripcion"])) {
				$descripcion = $parametros["descripcion"];
			}

			if (isset($parametros["archivado"])) {
				$archivado = 1;
				$hijos = $db->getHijos($parametros["id"]);
				$db->archivarActivos($hijos, $archivado, $token['data']);
			} else {
				$archivado = 0;
			}

			if (isset($parametros["externo"])) {
				$externo = 1;
			} else {
				$externo = 0;
			}

			if (isset($parametros["expuesto"])) {
				$expuesto = 1;
			} else {
				$expuesto = 0;
			}

			$activo = $db->getActivo($parametros["id"]);

			$resultado = $db->editActivo($parametros["id"], $parametros[NOMBRE], $token['data'], $parametros["activo_id"], $descripcion, $archivado, $externo, $expuesto);

			checkChangesActivo($activo, $parametros["nombre"], $descripcion, $archivado, $expuesto, $token);
		} else {
			$resultado = $db->editActivo($parametros["id"], $parametros[NOMBRE], $token['data']);
		}

		$response_data = [];
		if ($resultado == 'NOPROPIETARIO') {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = 'No eres el propietario de este activo y no puedes editarlo.';
		} else {
			$response_data[ERROR] = false;
			$response_data[MESSAGE] = 'Activo editado correctamente.';
		}

		$response->getBody()->write(json_encode($response_data));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(200);
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Ha ocurrido un error interno. Por favor, inténtalo más tarde.';

		error_log($e->getMessage());

		$response->getBody()->write(json_encode($response_data));
		return $response->withHeader(CONTENT_TYPE, JSON)->withStatus(500);
	}
});

/**
 * @OA\Post(
 *     path="/api/cloneActivo",
 *     tags={"Activos"},
 *     summary="Clona un activo pasado en el body",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="bia", type="string"),
 *             @OA\Property(property="ecr", type="string"),
 *             @OA\Property(property="id", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Clona un activo pasado en el body")
 * )
 */
$app->post('/api/cloneActivo', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;
	$msg = "Se ha clonado el servicio sin ningún error.";
	$copybia = false;
	$copyecr = false;
	$db = new Activos(DB_SERV);
	$parametros = $request->getParsedBody();
	if (isset($parametros['bia'])) {
		$copybia = true;
	}
	if (isset($parametros['ecr'])) {
		$copyecr = true;
	}
	if (isset($parametros["id"])) {
		$servicio = $db->getActivo($parametros["id"]);
		$activos = $db->getTree($parametros);
		array_push($activos, $servicio[0]);
		$activos = array_reverse($activos);
		foreach ($activos as $activo) {
			if (!isset($activo['padre'])) {
				$activo['padre'] = "undefined";
			} else {
				$index = array_search($activo['padre'], array_column($activos, 'id'));
				$activo['padre'] = $db->getActivoByNombre($activos[$index]['nombre'] . "_$parametros[sufijo]")[0]['id'];
			}
			$nombre = $activo[NOMBRE] . "_" . $parametros['sufijo'];
			$resultado = $db->newActivo($nombre, $activo['tipo'], $activo['padre'], $token['data']);
			if ($resultado == "ACTIVO_FAILURE") {
				$msg = "Ha ocurrido un error contacte con el administrador.";
			}
			if ($activo['tipo'] == 42 & $copybia) {
				$bia = $db->getBia($activo['id']);
				if (isset($bia[0]['meta_value'])) {
					$bia = json_decode($bia[0]['meta_value'], true);
					$servicio = $db->getActivoByNombre($activo['nombre'] . "_$parametros[sufijo]")[0]['id'];
					$db->setMetaValue($servicio, $bia, 'bia');
				}
			}
			if ($activo['tipo'] == 33 & $copyecr) {
				$ecr = $db->getFechaEvaluaciones($activo['id']);
				if (isset($ecr[0]['id'])) {
					$preguntas = $db->getPreguntasEvaluacionByFecha($ecr[0]["id"]);
					$comentarios =  $db->getComentariosEvaluacionById($ecr[0]["id"] + 1);
					$ecr = json_decode($preguntas[0]['preguntas'], true);
					$comentarios = json_decode($comentarios[0]['comentarios'], true);
					$sistema = $db->getActivoByNombre($activo['nombre'] . "_$parametros[sufijo]")[0]['id'];
					$db->setMetaValue($sistema, $ecr, 'preguntas');
					$db->setMetaValue($sistema, $comentarios, 'comentarios');
				}
			}
		}
	} else {
		$response_data[ERROR] = true;
		$msg = "No se ha introducido ningún activo que clonar.";
	}
	$response_data[MESSAGE] = $msg;
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteActivo",
 *     tags={"Activos"},
 *     summary="Elimina un activo pasado en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="idp", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Elimina un activo pasado en el body.")
 * )
 */
$app->post('/api/deleteActivo', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$db = new Activos(DB_SERV);
	$logs = new Logs("octopus_logs");
	$db_users = new Usuarios(DB_USER);
	$user = $db_users->getUser($token['data']);
	$additional_access = checkForAdditionalAccess($user[0]["roles"], $request->getUri()->getPath());
	$user = $user[0]["id"];
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	$comprobacion = comprobarPentestsExistente($parametros['id'], $db, $token);
	if (!$comprobacion["error"]) {
		$comprobacion = comprobarRevisionExistente($parametros['id'], $db, $token);
	}
	if ($comprobacion["error"]) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = $comprobacion["message"];
		$response->getBody()->write(json_encode($response_data));
		return $response
			->withHeader(CONTENT_TYPE, JSON)
			->withStatus(200);
	}
	$activo  = $db->getActivo($parametros['id'], $token['data']);
	if (!isset($activo[0]) || $activo[0]['tipo'] === 124 || $activo[0]['tipo'] === 123) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "No se puede eliminar un activo del tipo área.";
	} else {
		$activos = $db->getHijos($activo[0]['id']);
		foreach ($activos as $activo) {
			$dependencias = $db->getDependenciasActivo($activo['id']);
			if (count($dependencias) < 2 || $activo["id"] == $parametros["id"]) {
				$db->delActivo($activo['id'], $user, $additional_access);
				$logs->addDeleteLog($activo, $user);
			} elseif (count($dependencias) > 1) {
				foreach ($dependencias as $dependencia) {
					foreach ($activos as $activoDependencia) {
						if ($dependencia['padre_id'] === $activoDependencia['id']) {
							$db->delParentesco($activo['id'], $dependencia['padre_id'], $token['data'], $additional_access);
							break;
						}
					}
				}
				$dependencias = $db->getDependenciasActivo($activo['id']);
				if (count($dependencias) == 0) {
					$db->delActivo($activo['id'], $user, $additional_access);
					$logs->addDeleteLog($activo, $user);
				}
			}
		}
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/savebia/",
 *     tags={"Evaluación / EAE"},
 *     summary="Guarda la información del cumplimiento del BIA de un servicio.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Guarda la información del cumplimiento del BIA de un servicio.")
 * )
 */
$app->post('/api/savebia/', function (Request $request, Response $response) {
	$parametros = $request->getParsedBody();
	try {
		if (isset($parametros)) {
			$db = new DbOperations(DB_SERV);
			$db_activo = new Activos(DB_SERV);
			$db_users = new Usuarios(DB_USER);
			$token = $request->getAttribute(TOKEN);
			$user = $db_users->getUser($token['data']);
			if (!isset($user[0])) {
				throw new RouteException("Usuario no encontrado.");
			}
			$user = $user[0]["id"];
			$db->clearBia($parametros['id']);
			$db->setMetaValue($parametros['id'], $parametros, 'bia', $user);
			$bia = $db->getBia($parametros['id']);
			$biacalculado = calcularBia($bia);
			$activo = $db_activo->getActivo($parametros['id']);
			if ($biacalculado["Con"]["Max"] >= 3 || $biacalculado["Int"]["Max"] >= 3 || $biacalculado["Dis"]["Max"] >= 3) {
				editHijosCriticos($db_activo, $activo[0], 1);
			} else {
				editHijosCriticos($db_activo, $activo[0], 0);
			}
			if ($parametros[41] == "0") {
				$db_activo->editExposicion($activo[0]["id"], 1);
			}
		}
		$response_data[MESSAGE] = "BIA guardado correctamente.";
		$response_data[ERROR] = false;
	} catch (Exception $e) {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = "Error al guardar el BIA " . $e->getMessage();
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/savereportkpms",
 *     tags={"Kpms"},
 *     summary="Guarda el reporte de KPMS y manda un correo electrónico con la copia del reporte al usuario.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="activo", type="string"),
 *             @OA\Property(property="reporte", type="string"),
 *             @OA\Property(property="direccion", type="string"),
 *             @OA\Property(property="area", type="string"),
 *             @OA\Property(property="KPM04", type="string"),
 *         )
 *     ),
 * 		@OA\Parameter(
 *         name="kpm04Ciso",
 *         in="query",
 *         required=true,
 *         description="KPM04 obtenido de 11Cert.",
 *         @OA\Schema(type="string")
 *     ),
 * 		@OA\Parameter(
 *         name="kpm05Ciso",
 *         in="query",
 *         required=true,
 *         description="KPM04 obtenido de 11Cert.",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(response="200", description="Guarda el reporte de KPMS y manda un correo electrónico con la copia del reporte al usuario.")
 * )
 */
$app->post('/api/savereportkpms', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$parametros = $request->getParsedBody();

	if (isset($parametros)) {
		$db = new Usuarios(DB_USER);
		$user = $db->getUser($token['data']);
		$db = new Activos(DB_SERV);
		$metricas = obtenerMetricasAreas($db, array("areas" => $parametros["activo"]));
		$activo = $db->getActivo($parametros["activo"]);
		if (isset($activo[0])) {
			$reporte = $activo[0]["nombre"];
			if ($activo[0]["tipo"] == 124) {
				$direccion["nombre"] = $activo[0]["nombre"];
			} else {
				$direccion = getParentescobySistemaId($activo, 124);
			}
			if ($activo[0]["tipo"] == 123) {
				$area["nombre"] = $activo[0]["nombre"];
			} else {
				$area = getParentescobySistemaId($activo, 123);
			}
		}
		unset($parametros["activo"]);
		$parametros["reporte"] = $reporte;
		$parametros["direccion"] = $direccion["nombre"];
		$parametros["area"] = $area["nombre"];
		$db = new DbOperations('octopus_kpms');
		$db->savekpms($user[0]["id"], $parametros);
		if (isset($parametros["KPM04"])) {
			$tipo = 'métricas';
		} elseif (isset($parametros["KPM59A"])) {
			$tipo = 'csirt';
		} else {
			$tipo = 'madurez';
		}
		$dbKPMs = new Activos(DB_KPMS);
		if ($tipo == 'métricas' && ($metricas[0]["SisInformacion"] != $parametros["KPM04"] || $metricas[0]["SisInformacionAct"] != $parametros["KPM05"])) {
			$argumento["tipo"] = "metricas";
			$idReporte = $dbKPMs->getLastReportKpms($argumento, $user[0]["id"]);
			if ($parametros["KPM04"] == "") {
				$parametros["KPM04"] = "Campo sin rellenar/Null";
			}
			if ($parametros["KPM05"] == "") {
				$parametros["KPM05"] = "Campo sin rellenar/Null";
			}
			$kpmsInfo = "<p class=MsoNormal style='line-height:150%;width: 50%;float: left'>KPM04 Reportado : " . $parametros["KPM04"] . "</p>
							<p class=MsoNormal style='line-height:150%;width: 50%;float: left'>KPM05 Reportado : " . $parametros["KPM05"] . "</p>
							<p class=MsoNormal style='line-height:150%;width: 50%;float: left'>KPM04 11Cert : " . $metricas[0]["SisInformacion"] . "</p>
							<p class=MsoNormal style='line-height:150%;width: 50%;float: left'>KPM05 11Cert : " . $metricas[0]["SisInformacionAct"] . "</p>";
			$bodyAlerta = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'emailAlertaKpms.phtml');
			$bodyAlerta = str_replace("{user}", $user[0]["email"], $bodyAlerta);
			$bodyAlerta = str_replace("{area}", $parametros["area"], $bodyAlerta);
			$bodyAlerta = str_replace("{idReporte}", $idReporte[0]["id"], $bodyAlerta);
			$bodyAlerta = str_replace("{kpms}", $kpmsInfo, $bodyAlerta);
			$mail = new Email;
			$mail->sendmail("eduardo.lleracalvo@telefonica.com", '[11CerTools] Alerta de incongruencia en reporte de KPMs', $bodyAlerta, 'Body in plain text for non-HTML mail clients');
		}
		$mail = new Email;
		$body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'htmlemailkpms.phtml');
		$body = str_replace("{tipo}", $tipo, $body);
		$body = str_replace("{kpms}", implode("</p><p class=MsoNormal style='line-height:150%;width: 50%;float: left'>", array_map(function ($v, $k) {
			return $k . " : " . $v;
		}, $parametros, array_keys($parametros))) . '</p>', $body);
		$mail->sendmail($user[0]['email'], '[11CerTools] Copia del reporte KPMS', $body, 'Body in plain text for non-HTML mail clients');
	}
	$response_data[ERROR] = false;
	$response_data[MESSAGE] = "Reporte de KPMS enviado satisfactoriamente.";
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\get(
 *     path="/api/sendEmail",
 *     tags={"email"},
 *     summary="Envía un email con el contenido.",
 *     @OA\Response(response="200", description="Crea la nueva evaluación del análisis de riesgo dado un sistema.")
 * )
 */
$app->post('/api/sendEmail', function (Request $request, Response $response) {
	$mail = new Email();
	$params = $request->getParsedBody();

	$info = $params['body']; // Usar el cuerpo del mensaje pasado en la solicitud
	$body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'emailInformacion.phtml');
	$body = str_replace("{info}", $info, $body);

	$mailResponse = $mail->sendmail(
		$params['to'],
		//$params['cc'],
		$params['asunto'],
		$body,
		$params['alternbody']
	);

	$response->getBody()->write(json_encode($mailResponse));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newEval/{id}",
 *     tags={"Evaluaciones"},
 *     summary="Crea la nueva evaluación del análisis de riesgo dado un sistema.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="fecha", type="string"),
 *             @OA\Property(property="version", type="string"),
 *             @OA\Property(property="evaluate", type="string"),
 *             @OA\Property(property="comment", type="string"),
 *             @OA\Property(property="editEval", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea la nueva evaluación del análisis de riesgo dado un sistema.")
 * )
 */
$app->post('/api/newEval/{id}', function (Request $request, Response $response, array $args) {
	$response_data[ERROR] = false;
	$id = $args['id'];
	$parametros = $request->getParsedBody();
	$db = new DbOperations(DB_SERV);
	if (isset($parametros['fecha']) || isset($parametros['version'])) {
		if (!isset($parametros['fecha'])) {
			$parametros['fecha'] = null;
		}
		if (!isset($parametros['version'])) {
			$parametros['version'] = null;
		}
		$db->editEval($parametros['fecha'], $parametros['version'], $parametros["evaluate"]);
		$responde[ERROR] = false;
	} else {
		if (isset($parametros["evaluate"])) {
			$db->setMetaValue($id, $parametros["evaluate"], PREGUNTAS);
		} else {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = "No se ha respondido ninguna pregunta del cuestionario.";
		}
		if (isset($parametros["comment"])) {
			$parametros["comment"] = array_filter($parametros["comment"], function ($value) {
				return is_string($value) && $value !== '';
			});
			if (count($parametros["comment"]) !== 0) {
				$db->setMetaValue($id, $parametros["comment"], 'comentarios');
			}
		}
	}
	if (!isset($parametros['editEval'])) {
		updateListPac($id);
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/saveEval/{id}",
 *     tags={"Evaluaciones"},
 *     summary="Guarda los cambios que se han hecho en la evaluación.",
 *     @OA\Response(response="200", description="Guarda los cambios que se han hecho en la evaluación.")
 * )
 */
$app->post('/api/saveEval/{id}', function (Request $request, Response $response, array $args) {
	$id = $args['id'];
	$parametros = $request->getParsedBody();
	$responde[ERROR] = null;

	$db = new DbOperations(DB_SERV);
	$responde[ERROR] = 3;

	$db->setMetaValue($id, $parametros, 'save_eval');
	if ($responde[ERROR] === 3) {
		$responde[ERROR] = false;
	}

	$response_data[ERROR] = $responde[ERROR];

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/updateCumpleKpm",
 *     tags={"Kiuwan"},
 *     summary="Actualiza el valor de cumple_kpm para una aplicación.",
 *     @OA\Parameter(
 *         name="app_name",
 *         in="query",
 *         required=true,
 *         description="El nombre de la aplicación en Kiuwan",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="cumple_kpm",
 *         in="query",
 *         required=true,
 *         description="Valor de cumple_kpm (0 o 1)",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="El valor de cumple_kpm ha sido actualizado."
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Parámetros inválidos o faltantes."
 *     )
 * )
 */
$app->post('/api/updateCumpleKpm', function (Request $request, Response $response) {
	$params = $request->getParsedBody();
	$app_name = isset($params['app_name']) ? trim($params['app_name']) : null;
	$cumple_kpm = isset($params['cumple_kpm']) ? trim($params['cumple_kpm']) : null;

	if (empty($app_name) || ($cumple_kpm !== '0' && $cumple_kpm !== '1')) {
		$errorResponse = [
			'error' => true,
			'message' => 'Parámetros inválidos o faltantes.',
		];
		$response->getBody()->write(json_encode($errorResponse));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(400);
	}

	try {
		$db = new Pentest(DB_SERV);
		$db->updateCumpleKpm($app_name, (int) $cumple_kpm);

		$response->getBody()->write(json_encode([
			'success' => true,
			'message' => 'El valor de cumple_kpm ha sido actualizado para la aplicación ' . $app_name
		]));

		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(200);
	} catch (Exception $e) {
		$errorResponse = [
			'error' => true,
			'message' => $e->getMessage(),
		];

		$response->getBody()->write(json_encode($errorResponse));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(400);
	}
});

/**
 * @OA\Post(
 *     path="/api/updateSonarKPM",
 *     tags={"Kiuwan"},
 *     summary="Actualiza el valor de cumple_kpm para una aplicación.",
 *     @OA\Parameter(
 *         name="app_name",
 *         in="query",
 *         required=true,
 *         description="El nombre de la aplicación en Kiuwan",
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Parameter(
 *         name="cumple_kpm",
 *         in="query",
 *         required=true,
 *         description="Valor de cumple_kpm (0 o 1)",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="El valor de cumple_kpm ha sido actualizado."
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Parámetros inválidos o faltantes."
 *     )
 * )
 */
$app->post('/api/updateSonarKPM', function (Request $request, Response $response) {
	try {
		$params = $request->getParsedBody();
		$slot_sonarqube = $params['slot_sonarqube'] ?? null;
		$cumple_kpm_sonar = $params['cumple_kpm_sonar'] ?? null;

		if (!$slot_sonarqube || ($cumple_kpm_sonar !== '0' && $cumple_kpm_sonar !== '1')) {
			throw new RouteException('Parámetros inválidos o faltantes.');
		}

		$db = new Pentest(DB_SERV);

		$db->updateSonarKPM($slot_sonarqube, (int) $cumple_kpm_sonar);

		$response->getBody()->write(json_encode([
			'success' => true,
			'message' => 'El valor de cumple_kpm_sonar ha sido actualizado para la aplicación ' . $slot_sonarqube
		]));

		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(200);
	} catch (Exception $e) {
		$errorResponse = [
			'error' => true,
			'message' => $e->getMessage(),
		];

		$response->getBody()->write(json_encode($errorResponse));
		return $response
			->withHeader('Content-Type', JSON)
			->withStatus(400);
	}
});

/**
 * @OA\Post(
 *     path="/api/newUser",
 *     tags={"Usuarios"},
 *     summary="Crea un usuario pasado en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="email", type="string"),
 *             @OA\Property(property="password", type="string"),
 *             @OA\Property(property="rol", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un usuario pasado en el body.")
 * )
 */
$app->post('/api/newUser', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	if (isset($parametros['email']) && isset($parametros['rol'])) {
		$db = new Usuarios(DB_USER);
		$resultado = $db->newUser($parametros['email'], null, $parametros['rol']);
		if (!$resultado[ERROR]) {
			$body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'htmlemailnewuser.phtml');
			$body = str_replace("{email}", $parametros['email'], $body);
			$body = str_replace("{password}", $resultado['authPass'], $body);
			$mail = new Email;
			$response_data = $mail->sendmail($parametros['email'], '[11CerTools] Se te ha creado un nuevo usuario', $body, 'Body in plain text for non-HTML mail clients');
			$response_data[MESSAGE] = 'Usuario creado correctamente, se le ha enviado un email con sus credenciales.';
			$response_data[ERROR] = false;
		} else {
			$response_data[ERROR] = $resultado[ERROR];
			$response_data[MESSAGE] = $resultado[MESSAGE];
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Faltan parámetros para ejecutar la acción.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/newRol",
 *     tags={"Roles"},
 *     summary="Crea un nuevo rol pasado en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="nombre", type="string"),
 *             @OA\Property(property="descripcion", type="string")
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea un nuevo rol pasado en el body.")
 * )
 */
$app->post('/api/newRol', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();

	if (isset($parametros['name']) && isset($parametros['color']) && isset($parametros['additionalAccess'])) {
		$db = new Usuarios(DB_USER);
		$resultado = $db->newRol($parametros['name'], $parametros['color'], $parametros['additionalAccess']);
		if (!$resultado[ERROR]) {
			$response_data[MESSAGE] = 'Rol creado exitosamente.';
		} else {
			$response_data[ERROR] = $resultado[ERROR];
			$response_data[MESSAGE] = $resultado[MESSAGE];
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Faltan parámetros para ejecutar la acción.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editUser",
 *     tags={"Usuarios"},
 *     summary="Edita un usuario pasado en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *             @OA\Property(property="email", type="string"),
 *             @OA\Property(property="password", type="string"),
 *             @OA\Property(property="rol", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita un usuario pasado en el body.")
 * )
 */
$app->post('/api/editUser', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	if (isset($parametros['rol']) && isset($parametros['id'])) {
		$db = new Usuarios(DB_USER);
		$usuario = $db->getUser($parametros['id']);
		if (isset($usuario[0]) && $usuario[0]['roles'] !== $parametros['rol'] || $usuario[0]['email'] !== $parametros['email']) {
			$user_id = $usuario[0]['id'];
			$user_rol = $parametros['rol'];
			$db->editUser($user_id, $user_rol);
			$response_data[MESSAGE] = 'Se ha editado el usuario correctamente.';
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Faltan parámetros para ejecutar la acción.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/pentestRequest",
 *     tags={"Formulario Pentest"},
 *     summary="Crea una solicitud de pentest.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="user_id", type="int", description="ID del usuario."),
 *             @OA\Property(property="servicio_a_pentest", type="string", description="Dirección, Área, Unidad del Servicio a Pentestear."),
 *             @OA\Property(property="nombre_servicio", type="string", description="Nombre del Servicio."),
 *             @OA\Property(property="version_servicio", type="string", description="Versión del servicio/producto."),
 *             @OA\Property(property="tipo_pentest", type="string", description="Tipo de pentest."),
 *             @OA\Property(property="fecha_solicitud", type="string", description="Fecha de solicitud del pentest."),
 *             @OA\Property(property="fecha_inicio", type="string", description="Fecha de inicio del pentest."),
 *             @OA\Property(property="fecha_fin", type="string", description="Fecha de fin del pentest."),
 *             @OA\Property(property="req_informe", type="int", description="Requerimiento de informe."),
 *             @OA\Property(property="horas_pentest", type="int", description="Ventana de tiempo."),
 *             @OA\Property(property="proyecto_jira", type="string", description="Proyecto de Jira del servicio."),
 *             @OA\Property(property="resp_pentest", type="string", description="Responsable para asociarle issues."),
 *             @OA\Property(property="persona_soporte", type="string", description="Persona de soporte."),
 *             @OA\Property(property="aviso_incump", type="int", description="Aviso de incumplimiento de plazos."),
 *             @OA\Property(property="documentacion", type="string", description="Documentación.")
 *         )
 *     ),
 *     @OA\Response(response="200", description="Crea una solicitud de pentest.")
 * )
 */
$app->post('/api/pentestRequest', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;

	// Obtener los parámetros de la solicitud
	$parametros = $request->getParsedBody();

	// Obtener el ID del usuario
	$db = new Usuarios(DB_USER);
	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {
		$parametros['user_id'] = $usuario[0]['id']; // Añadir el ID del usuario a los parámetros

		// Lógica para almacenar el formulario de pentest
		$dbPentestRequest = new PentestRequest(DB_SERV);
		$result = $dbPentestRequest->crearFormulario($parametros);

		// Si la creación del formulario es exitosa, proceder con el envío de correo
		if (isset($result[ERROR]) && $result[ERROR]) {
			$response_data[ERROR] = true;
			$response_data[MESSAGE] = 'Error al crear el formulario de pentest.';
		} else {
			// Enviar correo de confirmación al usuario
			$body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'emailInformacion.phtml');

			$info = "
				Estimado usuario,<br><br>
                Se ha enviado una nueva solicitud de pentest con la siguiente información:<br>
				<strong>Nombre del Producto:</strong> " . implode(', ', json_decode($parametros['nombre_servicio'], true)) . "<br>
				<strong>Fecha de Inicio:</strong> {$parametros['fecha_inicio']}<br>
				<strong>Fecha de Fin:</strong> {$parametros['fecha_fin']}<br>
				<strong>Tipo de Pentest:</strong> {$parametros['tipo_pentest']}<br>
				<strong>Tipo de Entorno:</strong> {$parametros['tipo_entorno']}<br>
				<strong>Franja Horaria:</strong> {$parametros['franja_horaria']}<br>
				<strong>Horas de Pentest:</strong> {$parametros['horas_pentest']}<br>
				<strong>Documentación:</strong> " . implode(', ', json_decode($parametros['documentacion'], true)) . "<br>
			";

			$body = str_replace("{info}", $info, $body);

			$asunto = 'Nueva solicitud de pentest enviada';

			$to = $usuario[0]['email'];

			$cc = 'desarrolloseguro.cdco@telefonica.com';

			$mail = new Email();
			$mailResponse = $mail->sendmailEvs(
				$to,
				$cc,
				$asunto,
				$body,
				'Se ha enviado una nueva solicitud de pentest.'
			);

			// Verificar si el correo fue enviado correctamente
			if ($mailResponse[ERROR]) {
				$response_data = [
					'status' => true,  // El formulario se ha enviado, pero hubo un error con el correo
					'message' => 'Formulario enviado, pero ocurrió un error al enviar el correo: ' . $mailResponse[MESSAGE]
				];
			} else {
				$response_data = [
					'status' => true,
					'message' => 'Formulario enviado exitosamente.'
				];
			}
		}
	} else {
		$response_data = [
			'status' => false,
			'message' => 'No se pudo obtener el ID del usuario.'
		];
	}

	// Retorna la respuesta al frontend
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editEndpointsByRole",
 *     tags={"Roles"},
 *     summary="Edita los endpoints asignados a un rol específico.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"rol", "endpoints", "allow"},
 *             @OA\Property(property="rol", type="string", description="ID del rol"),
 *             @OA\Property(property="endpoints", type="array", @OA\Items(type="string"), description="Lista de IDs de endpoints"),
 *             @OA\Property(property="allow", type="boolean", description="Indica si se permite o no el acceso a los endpoints")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Respuesta exitosa",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="boolean", description="Indica si hubo un error"),
 *             @OA\Property(property="message", type="string", description="Mensaje de la respuesta")
 *         )
 *     ),
 *     @OA\Response(
 *         response=400,
 *         description="Solicitud incorrecta",
 *         @OA\JsonContent(
 *             @OA\Property(property="error", type="boolean", description="Indica si hubo un error"),
 *             @OA\Property(property="message", type="string", description="Mensaje de error")
 *         )
 *     )
 * )
 */
$app->post('/api/editEndpointsByRole', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	if (isset($parametros['roleId']) && isset($parametros['endpoints']) && isset($parametros['allow'])) {
		$db = new Usuarios(DB_USER);
		$db->editEndpointsByRole($parametros['roleId'], $parametros['endpoints'], $parametros['allow']);
		$response_data[MESSAGE] = 'Se han editado los endpoints del rol correctamente.';
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Faltan parámetros para ejecutar la acción.';
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/aceptarSolicitudPentest",
 *     tags={"Formulario Pentest"},
 *     summary="Acepta una solicitud de pentest.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="int", description="ID de la solicitud."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Guarda una solicitud de pentest.")
 * )
 */
$app->post('/api/aceptarSolicitudPentest', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;

	// Obtener los parámetros de la solicitud
	$parametros = $request->getParsedBody();

	// Obtener el ID del usuario
	$db = new Usuarios(DB_USER);
	$dbPentestRequest = new PentestRequest(DB_SERV);
	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {

		$result = $dbPentestRequest->aceptarSolicitudPentest($parametros["id"]);

		// Verifica el resultado y establece la respuesta adecuada
		if ($result[ERROR]) {
			$response_data = [
				'status' => false,
				'message' => 'Error al aceptar el formulario de pentest.'
			];
		} else {
			$response_data = [
				'status' => true,
				'message' => 'Formulario aceptado exitosamente.'
			];
		}
	} else {
		$response_data = [
			'status' => false,
			'message' => 'No se pudo obtener el ID del usuario.'
		];
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/editRol",
 *     tags={"Roles"},
 *     summary="Edita un rol existente.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="nombre", type="string"),
 *             @OA\Property(property="color", type="string")
 *         )
 *     ),
 *     @OA\Response(response="200", description="Edita un rol existente.")
 * )
 */
$app->post('/api/editRol', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();

	if (isset($parametros['id']) && (isset($parametros['name']) || isset($parametros['color']) || isset($parametros['additionalAccess']))) {
		if ($parametros['additionalAccess']) {
			$parametros['additionalAccess'] = 1;
		} else {
			$parametros['additionalAccess'] = 0;
		}
		$db = new Usuarios(DB_USER);
		$resultado = $db->editRol($parametros['id'], $parametros['additionalAccess'], $parametros['name'] ?? null, $parametros['color'] ?? null);
		if (!$resultado[ERROR]) {
			$response_data[MESSAGE] = 'Rol editado exitosamente.';
		} else {
			$response_data[ERROR] = $resultado[ERROR];
			$response_data[MESSAGE] = $resultado[MESSAGE];
		}
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Faltan parámetros para ejecutar la acción.';
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/rechazarSolicitudPentest",
 *     tags={"Formulario Pentest"},
 *     summary="Rechaza una solicitud de pentest.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="int", description="ID de la solicitud."),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Guarda una solicitud de pentest.")
 * )
 */
$app->post('/api/rechazarSolicitudPentest', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;

	$parametros = $request->getParsedBody();
	$db = new Usuarios(DB_USER);
	$db_pentest_req = new PentestRequest(DB_SERV);
	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {
		$result = $db_pentest_req->rechazarSolicitudPentest($parametros["id"]);

		if (!$result['status']) {
			$response_data = [
				'status' => false,
				'message' => 'Error al rechazar el formulario de pentest.'
			];
		} else {
			$solicitudData = $result['solicitudData'];

			$body = file_get_contents('..' . DIRECTORY_SEPARATOR . TEMPLATE_DIR . DIRECTORY_SEPARATOR . 'emailInformacion.phtml');

			$nombre_servicio = isset($solicitudData['nombre_servicio']) ? json_decode($solicitudData['nombre_servicio'], true) : [];
			if (!is_array($nombre_servicio)) {
				$nombre_servicio = [$solicitudData['nombre_servicio']];
			}

			$documentacion = isset($solicitudData['documentacion']) ? json_decode($solicitudData['documentacion'], true) : [];
			if (!is_array($documentacion)) {
				$documentacion = [$solicitudData['documentacion']];
			}

			$info = "
                Estimado usuario,<br><br>
                Lamentamos informarle que su solicitud de pentest ha sido rechazada.<br>
                <strong>Nombre del Producto:</strong> " . implode(', ', $nombre_servicio) . "<br>
                <strong>Fecha de Inicio:</strong> " . ($solicitudData['fecha_inicio'] ?? 'N/A') . "<br>
                <strong>Fecha de Fin:</strong> " . ($solicitudData['fecha_fin'] ?? 'N/A') . "<br>
                <strong>Tipo de Pentest:</strong> " . ($solicitudData['tipo_pentest'] ?? 'N/A') . "<br>
                <strong>Tipo de Entorno:</strong> " . ($solicitudData['tipo_entorno'] ?? 'N/A') . "<br>
                <strong>Franja Horaria:</strong> " . ($solicitudData['franja_horaria'] ?? 'N/A') . "<br>
                <strong>Horas de Pentest:</strong> " . ($solicitudData['horas_pentest'] ?? 'N/A') . "<br>
                <strong>Documentación:</strong> " . implode(', ', $documentacion) . "<br>
            ";

			$body = str_replace("{info}", $info, $body);

			$asunto = 'Rechazo de solicitud de pentest';
			$to = $usuario[0]['email'];

			$mail = new Email();
			$mailResponse = $mail->sendmail(
				$to,
				$asunto,
				$body,
				'Su solicitud de pentest ha sido rechazada.'
			);

			if ($mailResponse[ERROR]) {
				$response_data = [
					'status' => true,
					'message' => 'Solicitud rechazada, pero ocurrió un error al enviar el correo: ' . $mailResponse[MESSAGE]
				];
			} else {
				$response_data = [
					'status' => true,
					'message' => 'Solicitud rechazada exitosamente y correo enviado.'
				];
			}
		}
	} else {
		$response_data = [
			'status' => false,
			'message' => 'No se pudo obtener el ID del usuario.'
		];
	}

	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader('Content-Type', JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *    path="/api/asignPentester",
 *    tags={"Formulario Pentest"},
 *    summary="Asigna un pentester a una solicitud de pentest.",
 *    @OA\RequestBody(
 * 	      required=true,
 * 	      @OA\JsonContent(
 * 			  type="object",
 * 			  @OA\Property(property="id", type="int", description="ID de la solicitud."),
 * 			  @OA\Property(property="pentester", type="int", description="mail del pentester.")
 * 		  )
 *   ),
 *  @OA\Response(response="200", description="Asigna un pentester a un pentest.")
 * )
 */
$app->post('/api/asignPentester', function (Request $request, Response $response) {
	$token = $request->getAttribute(TOKEN);
	$response_data = [];
	$response_data[ERROR] = false;

	$parametros = $request->getParsedBody();
	$db = new Usuarios(DB_USER);
	$db_pentest = new Pentest(DB_SERV);

	$usuario = $db->getUser($token['data']);

	if (isset($usuario[0]['id'])) {
		$email = $usuario[0]['email'];
		$result = $db_pentest->asignPentester([
			"id" => $parametros["id"],
			"resp_pentest" => $email
		]);

		if ($result["error"]) {
			$response_data = [
				'status' => false,
				'message' => $result["message"]
			];
		} else {
			$response_data = [
				'status' => true,
				'message' => 'Pentester asignado exitosamente.'
			];
		}
	} else {
		$response_data = [
			'status' => false,
			'message' => 'No se pudo obtener el ID del usuario.'
		];
	}

	$response->getBody()->write(json_encode($response_data));
	return $response->withHeader('Content-Type', JSON)->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteRol",
 *     tags={"Roles"},
 *     summary="Elimina un rol especificado por su ID.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="id", type="integer", description="ID del rol a eliminar")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Rol borrado correctamente.",
 *         @OA\JsonContent(
 *             type="object",
 *             @OA\Property(property="error", type="boolean", example=false),
 *             @OA\Property(property="message", type="string", example="Rol borrado correctamente.")
 *         )
 *     )
 * )
 */
$app->post('/api/deleteRol', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	if (isset($parametros['id'])) {
		$db = new Usuarios(DB_USER);
		$db->deleteRol($parametros['id']);
		$response_data[MESSAGE] = 'Rol borrado correctamente.';
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Falta el parámetro ID de rol para borrar.';
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});

/**
 * @OA\Post(
 *     path="/api/deleteUser",
 *     tags={"Usuarios"},
 *     summary="Elimina un usuario pasado en el body.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             @OA\Property(property="id", type="string"),
 *         )
 *     ),
 *     @OA\Response(response="200", description="Elimina un usuario pasado en el body.")
 * )
 */
$app->post('/api/deleteUser', function (Request $request, Response $response) {
	$response_data = [];
	$response_data[ERROR] = false;
	$parametros = $request->getParsedBody();
	if (isset($parametros['id'])) {
		$db = new Usuarios(DB_USER);
		$db->delUser($parametros['id']);
		$response_data[MESSAGE] = 'Usuario borrado correctamente.';
	} else {
		$response_data[ERROR] = true;
		$response_data[MESSAGE] = 'Falta el parámetro ID de usuario para borrar.';
	}
	$response->getBody()->write(json_encode($response_data));
	return $response
		->withHeader(CONTENT_TYPE, JSON)
		->withStatus(200);
});


/**
 * Middleware de sincronización de rutas de la API.
 *
 * - Ejecuta una única vez al día (controlado por la tabla de logs).
 * - Extrae todas las rutas registradas en Slim, normalizando los parámetros dinámicos ({param}) a '*'.
 * - Obtiene los métodos HTTP asociados (con fallback por si Slim no detecta PATCH/DELETE).
 * - Lee los comentarios PHPDoc de cada ruta para extraer `summary` (descripción) y `tags` de la especificación OpenAPI.
 * - Inserta o actualiza cada ruta en la tabla `endpoints`, evitando duplicados.
 *
 * Objetivo: mantener actualizada la base de datos de rutas protegidas para el sistema de permisos.
 */
$app->add(function ($request, $handler) use ($app) {
	$now = new DateTime('now', new DateTimeZone('UTC'));

	$logsDB  = new Logs('octopus_logs');
	$lastUpdate = $logsDB->getLastRouteUpdate();

	$shouldUpdate = !$lastUpdate || (new DateTime($lastUpdate))->format('Y-m-d') !== $now->format('Y-m-d');

	if (!$shouldUpdate) {
		return $handler->handle($request);
	}

	$logsDB->updateRouteLog();

	$routeCollector = $app->getRouteCollector();
	$routes = $routeCollector->getRoutes();

	$dbUsers = new Usuarios(DB_USER);

	foreach ($routes as $route) {
		$methods = $route->getMethods();
		$pattern = $route->getPattern();

		// Sustituir cualquier parámetro dinámico por *
		$pattern = preg_replace('/\{[^}]+\}/', '*', $pattern);

		// Obtener el callable de la ruta
		$callable = $route->getCallable();
		$docComment = '';

		if (is_callable($callable)) {
			try {
				$reflection = new ReflectionFunction($callable);
				$docComment = $reflection->getDocComment() ?: '';
			} catch (ReflectionException $e) {
				$docComment = '';
			}
		}

		// Extraer la descripción de OA
		$description = '';
		if (preg_match('/summary="([^"]+)"/', $docComment, $matches)) {
			$description = $matches[1];
		}

		// Extraer los tags de OA
		$tags = [];
		if (preg_match('/tags=\{([^}]+)\}/', $docComment, $matches)) {
			$tags = array_map(function ($tag) {
				return stripslashes(trim($tag, '"'));
			}, preg_split('/\s*,\s*/', $matches[1]));
		}
		$tags = json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		// En caso de métodos vacíos por inconsistencias de Slim, asignamos los habituales
		if (empty($methods)) {
			$methods = ['GET', 'POST', 'PATCH', 'DELETE'];
		}

		foreach ($methods as $method) {
			$dbUsers->insertOrUpdateRoute($pattern, $method, $description, $tags);
		}
	}

	return $handler->handle($request);
});

$app->addErrorMiddleware(false, true, true);

$app->run();
