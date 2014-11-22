<?php

$this->respond('GET', '/', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $service->render('index.phtml', array('action' => 'index', 'page' => 'cp/admin/index.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/bot', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $service->render('index.phtml', array('action' => 'bot', 'page' => 'cp/admin/bot/index.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/user/approve', function($request, $response, $service, $app) {
    if (verifySession($app) && checkPermission($app, 'panel.viewusers')) {
        $perms['approveUser'] = checkPermission($app, 'panel.approveuser');
        $perms['deleteUser'] = checkPermission($app, 'panel.deleteuser');
        $service->render('index.phtml', array('action' => 'user', 'page' => 'cp/admin/user/approval.phtml', 'perms' => $perms));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/user/manage', function($request, $response, $service, $app) {
    if (verifySession($app) && checkPermission($app, 'panel.viewusers')) {
        $service->render('index.phtml', array('action' => 'user', 'page' => 'cp/admin/user/manage.phtml'));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/ban', function($request, $response, $service, $app) {
    if (verifySession($app)) {

        $casted = array();
        $record = array();

        foreach ($record as $id => $ban) {
            $existing = $casted[$id];
            if ($existing === null) {
                $existing = array(
                    'id' => $id,
                    'issuer' => $ban['issuedBy'],
                    'kickmessage' => $ban['kickMessage'],
                    'issueDate' => $ban['issueDate'],
                    'type' => $ban['type'] === 0 ? "standard" : "extended",
                    'channels' => array($ban['channel'])
                );
            } else {
                $existing['channels'][] = $ban['channel'];
            }
            $casted[id] = $existing;
        }



        $service->render('index.phtml', array('action' => 'ban', 'page' => 'cp/admin/ban/index.phtml', 'bans' => $casted));
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/user', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        if (checkPermission($app, 'panel.viewusers')) {
            $perms['approveUser'] = checkPermission($app, 'panel.approveuser');
            $perms['deleteUser'] = checkPermission($app, 'panel.deleteuser');
            $service->render('index.phtml', array('action' => 'user', 'page' => 'cp/admin/user/approval.phtml', 'perms' => $perms));
        }
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('POST', '/user/list/unapproved', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        $perms['view'] = checkPermission($app, 'panel.viewuser');
        if ($perms['view']) {
            try {
                $statement = $app->auth_db->prepare("SELECT uuid as id,username as user,email FROM users WHERE approved=0 and verified=1");
                $statement->execute();
                $accounts = $statement->fetchAll();
            } catch (PDOException $ex) {
                logError($ex);
                $accounts = array();
            }
        } else {
            $accounts = array();
        }
        echo json_encode($accounts);
    } else {
        echo "failed";
    }
});

$this->respond('POST', '/user/approve/[:id]', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        try {
            $statement = $app->auth_db->prepare("UPDATE users SET approved=1 WHERE uuid=?");
            $statement->execute(array($request->id));
        } catch (PDOException $ex) {
            logError($ex);
        }
        $response->redirect("/user", 302);
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('POST', '/user/delete/[:id]', function($request, $response, $service, $app) {
    if (verifySession($app)) {
        try {
            $statement = $app->auth_db->prepare("DELETE FROM users WHERE uuid=?");
            $statement->execute(array($request->id));
        } catch (PDOException $ex) {
            logError($ex);
        }
    } else {
        $response->redirect("/auth/login", 302);
    }
});

$this->respond('GET', '/bans/get', function($request, $response, $service, $app) {
    $page = $request->param('p');
    if ($page === null) {
        $page = 1;
    }
    $page--;
    if (verifySession($app) && checkPermission($app, "")) {
        try {
            $statement = $app->auth_db->prepare("SELECT id, issuedBy, kickMessage, issueDate, channel, type "
                  . "FROM bans "
                  . "INNER JOIN banchannels ON bans.id = banId "
                  . "ORDER BY id "
                  . "LIMIT " . strval(intval($page) * 10) . ", 10");
            $statement->execute();
            $record = $statement->fetchAll(PDO::FETCH_ASSOC);
            $casted = array();
        } catch (Exception $ex) {
            return $ex;
        }

        foreach ($record as $ban) {
            if (!isset($casted[$ban['id']])) {
                $casted[$ban['id']] = array(
                    'id' => $ban['id'],
                    'issuer' => $ban['issuedBy'],
                    'kickmessage' => $ban['kickMessage'],
                    'issueDate' => $ban['issueDate'],
                    'type' => $ban['type'] === 0 ? "standard" : "extended",
                    'channels' => array($ban['channel'])
                );
            } else {
                $casted[$ban['id']]['channels'][] = $ban['channel'];
            }
        }
        return json_encode($casted);
    } else {
        return '{msg="failed"}';
    }
});
