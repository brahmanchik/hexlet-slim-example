<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Car;
use App\CarRepository;
use App\CarValidator;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;


session_start();

$container = new Container();

$container->set(\PDO::class, function () {
    $conn = new \PDO('sqlite:database.sqlite'); //соеденили с БД и подключились к ФАЙЛУ ИЛИ создали его если его не было
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});


$initFilePath = implode('/', [dirname(__DIR__), 'public/init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

$app = AppFactory::createFromContainer($container);
$app->add(MethodOverrideMiddleware::class);
$router = $app->getRouteCollector()->getRouteParser();
$app->addErrorMiddleware(true, true, true);




class Validator {
    public function validate (array $data) {
        if (strlen($data['name']) < 4) {
            return ['name' => "имя должно иметь больше 4 символов"];
        }
        return [];
    }
}
//Роутинг cars
$app->get('/ca  rs', function ($request, $response) {

    $carRepository = $this->get(CarRepository::class);

    $cars = $carRepository->getEntities();
    /** @var \Slim\Flash\Messages $flash */
    $flash = $this->get('flash');
    $flash->addMessage('success','hello world!');
    $messages = $flash->getMessages();

    $params = [
        'cars' => $cars,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/index.phtml', $params);
})->setName('cars.index');

$app->get('/cars/{id:[0-9]+}', function ($request, $response, $args) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];
    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'car' => $car,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/show.phtml', $params);
})->setName('cars.show');

$app->get('/cars/new', function ($request, $response) {
    $params = [
        'car' => new Car(),
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'cars/new.phtml', $params);
})->setName('cars.create');

$app->post('/cars', function ($request, $response) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $carData = $request->getParsedBodyParam('car');

    $validator = new CarValidator();
    $errors = $validator->validate($carData);

    if (count($errors) === 0) {
        $car = Car::fromArray([$carData['make'], $carData['model']]);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', 'Car was added successfully');
        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $params = [
        'car' => $carData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/new.phtml', $params);
})->setName('cars.store');


//Роутинг users
$app->get('/users', function ($request, $response) {
    $this->get('flash')->addMessage('success', 'User was added successfully');


    $str = __DIR__ . '/../templates/users/bd.txt';
    $listUsers = file_get_contents($str);
    $params = [
        'users' => $listUsers
    ];

    return $this->get('renderer')->render($response, "users/index.phtml", $params);
})->setName('users');

$app->delete('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];
    $str = __DIR__ . '/../templates/users/bd.txt';
    $listUsers = json_decode(file_get_contents($str), true);
    $users = array_filter($listUsers, function ($listUser) use ($id){
        return $listUser['id'] != $id;
    });

    $users = array_values($users);

    $jsonString = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    //dd($jsonString);
    file_put_contents($str, $jsonString);
    $this->get('flash')->addMessage('success', 'User has been deleted');
    return $response->withRedirect($router->urlFor('users'));
});

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $id = $args['id'];
    $str = __DIR__ . '/../templates/users/bd.txt';
    $listUsers = json_decode(file_get_contents($str), true);
    //dd($listUsers); //тут надо черех array filter ебануть и брать элемент с нужным id
    $user = array_filter($listUsers, function ($listUser) use ($id){
        return $listUser['id'] == $id;
    });
    $params = [
        'user' => array_values($user)[0],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('editUser');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router)  {
    $id = $args['id'];
    //надо найти пользователя с этим id
    $str = __DIR__ . '/../templates/users/bd.txt';
    $listUsers = json_decode(file_get_contents($str), true);
    //dd($listUsers); //тут надо черех array filter ебануть и брать элемент с нужным id
    $user = array_filter($listUsers, function ($listUser) use ($id){
        return $listUser['id'] == $id;
    });
    $user = array_values($user)[0];

    $data = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        // Ручное копирование данных из формы в нашу сущность
        $newName = $data['name'];
        //dd($data['name']);
        $this->get('flash')->addMessage('success', 'Успешно изменено имя');
        //тут надо изменить файл
        $newListUsers = array_map(function ($user) use ($id, $newName) {
            if ($user['id'] == $id) {
                $user['name'] = $newName;
            }
            return $user;
        }, $listUsers);
        $jsonString = json_encode($newListUsers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        //dd($newListUsers);
        file_put_contents($str, $jsonString);
        $url = $router->urlFor('editUser', ['id' => $user['id']]);
        return $response->withRedirect($url);
    }

    $params = [
        'user' => $user,
        'errors' => $errors
    ];

    $response = $response->withStatus(422);
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});





$app->get('/users/new', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = [
        'user' => ['name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => '', 'city' => ''],
        'errors' => [],
        'flash' => $messages
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
})->setName('new');


$app->get('/users/{id}', function ($request, $response, $args) {
    $id = (int) $args['id'];
    $file = __DIR__ . '/../templates/users/bd.txt';
    $list = [];
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $list = $json ? json_decode($json, true) : [];

        if (!is_array($list)) {
            $list = [];
        }
    }
    $result = array_filter($list, fn($user) => $user['id'] === $id);
    $user = reset($result);
    if (!$user) {
        $response = $response->withStatus(404);
        return $this->get('renderer')->render($response, "errors/404.phtml", [
            'message' => "Пользователь с id {$id} не найден"
        ]);
    }
    //var_dump($user);
    $params = [
        'user' => ['name' => $user['name'], 'email' => $user['email'], 'password' => $user['password'], 'passwordConfirmation' => $user['passwordConfirmation'], 'city' => $user['city'], 'id' => $user['id']],
        //тут еще надо наверно car предавать те которые пользователю принадлежат
        'car' => ['color' => '', 'model' => '']
    ];
    return $this->get('renderer')->render($response, "users/user.phtml", $params);
});


    $app->post('/users', function ($request, $response) use ($router) {
        $user = $request->getParsedBodyParam('user', []);

        $validator = new Validator();
        // Проверяем корректность данных
        $errors = $validator->validate($user);


        if (count($errors) === 0) {
            // Если данные корректны, то сохраняем, и выполняем редирект
            $file = __DIR__ . '/../templates/users/bd.txt';
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }

            // Читаем существующий массив
            $list = [];
            if (file_exists($file)) {
                $json = file_get_contents($file);
                $list = $json ? json_decode($json, true) : [];
                if (!is_array($list)) {
                    $list = [];
                }
            }

            // Добавляем нового пользователя в массив
            $lastId = 0;
            if (!empty($list)) {
                $lastUser = end($list);
                $lastId = $lastUser['id'] ?? 0;
            }

            $user['id'] = $lastId + 1;
            $list[] = $user;

            // Сохраняем весь массив обратно
            file_put_contents(
                $file,
                json_encode($list, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                LOCK_EX
            );


            $url = $router->urlFor('users'); // строим путь на основе имени маршрута
            return $response->withRedirect($url, 302);
        }

        $params = [
            'user' => ['name' => $user['name'], 'email' => $user['email'], 'password' => $user['password'],
                'passwordConfirmation' => $user['passwordConfirmation'], 'city' => $user['city']],
            'errors' => ['name' => $errors['name']]
        ];

        // Если возникли ошибки, то устанавливаем код ответа в 422 и рендерим форму с указанием ошибок
        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'users/new.phtml', $params);
});

        $app->post('/personal-page', function ($request, $response) use ($router) {
            // тут должен быть редирект на главную после авторизации просто на главной появляется имя пользователя
            $item = "test";
            $item = $request->getParsedBodyParam('user');

            // Добавление нового товара
            $_SESSION['name'] = $item['email'];
            return $response->withRedirect($router->urlFor('users'));
        });

        $app->post('/exit', function ($request, $response) use ($router) {
            // тут должен быть редирект на главную после авторизации просто на главной появляется имя пользователя
            $_SESSION = [];
            return $response->withRedirect($router->urlFor('users'));
        });

$app->run();