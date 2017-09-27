<?php
/**
 * $ab['var.ibadmin.rightsList']=
 * 'ibadmin_dbo_users_|Клиенты ДБО (просмотр);
 * ibadmin_dbo_edit_users_|Клиенты ДБО (добавление/изменение);
 * ibadmin_tasks_|Задачи;
 * ibadmin_logs_|Логирование;
 * ibadmin_upload_|Загрузка файлов;
 * ibadmin_account_|Счета клиентов;
 * ibadmin_dbo_admin_|Административное управление';
 */
/**
 * создание нового оператора ДБО
 * @param int $return - используется для внутреннего вызова(из скрипта)
 * @return mixed
 */

require_once 'ab/site/_conf_abs.php';

function write_new_client($in = 0)
{
    global $do, $QID, $ab, $ab_user;
    try {

        if (!$ab_user['id']) {
            throw new UserException($ab['ibadmin.bank_authError']);
        }

        if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

            cType::init();
            $abs_fir_type = cType::getAbsCtype('FIR');

            //error_log('$abs_fir_type ' . $abs_fir_type . ' post ' . $_POST['f_type_client']);
            $fir_type = Local::FIR_ABS;

            if (isset($_POST['f_type_client']) && ($_POST['f_type_client'] === "$fir_type" || $_POST['f_type_client'] === $fir_type)) {
                throw new UserException($ab['ibadmin.bank_error.oper_yur']);
            }

            $mess_create_log = (int)$in === 1 ? 'createNewNonClient' : ((int)$in === 2 ? 'createNewFizClient' : 'createNewClient');

            $_POST['f_cellphone'] = _prepare_phone($_POST['f_cellphone']);

            _check_client_by_phone($_POST['f_cellphone']);

            $_POST['f_ownerbd'] = _prepare_ownerbd($_POST['f_ownerbd']);

            $params = array(
                'f_lastname' => base64_encode($_POST['f_lastname']),
                'f_name' => base64_encode($_POST['f_name']),
                'f_fname' => base64_encode($_POST['f_fname']),
                'f_ownerbd' => $_POST['f_ownerbd'],
                'info' => ab_secure($_POST['f_lastname']) . ' ' . ab_secure($_POST['f_name']) . ' ' . ab_secure($_POST['f_fname']) . ' ' . $_POST['f_ownerbd']
            );

            $lastname = ab_secure($_POST['f_lastname']);
            $name = ab_secure($_POST['f_name']);
            $fname = ab_secure($_POST['f_fname']);
            $user_bd = ab_secure($_POST['f_ownerbd']);
            $info = $lastname . ' ' . $name . ' ' . $fname . ' ' . $user_bd;

            _check_client_personal_data_new($lastname, $name, $fname, $user_bd, $info);
            _check_client_personal_data($params);

            $abs_id = ab_secure($_POST['f_abs_ids']);
            unset($_POST['f_abs_ids']);

            if ($abs_id) {
                $r = ibadmin_DB::_check_client_by_absid($abs_id);
                if ($r) {
                    $mes = $ab['ibadmin.admin_error.createUser.personal_data'];
                    $mes = preg_replace('/\%fio/', ab_secure($_POST['f_lastname']) . ' ' . ab_secure($_POST['f_name']) . ' ' . ab_secure($_POST['f_fname']) . ' ' . $_POST['f_ownerbd'], $mes);
                    throw new UserException($mes);
                }
            }

            $do = "writeit";
            ab_edit_write(); // создание записи в таблице клиентов и установка $QID

            if (!$QID) {
                write_log($QID, $mess_create_log, 0);
                throw new UserException($ab['ibadmin.bank_error.user_no_add']);
            }

            write_log($QID, $mess_create_log, 1);

            $client_data = _get_client_data($QID);
            $phone = _get_phone($client_data);

            $paypass = _ibadmin_create_paypass($QID);
            // $text_paypass = preg_replace('/%p/', $paypass, $ab['ibadmin.bank_paypass_text_sms']);

            $login = _ibadmin_create_newlogin($QID, $client_data);
            // $text_login = preg_replace('/%l/', $login, $ab['ibadmin.bank_login_text_sms']);

            $text_sms = preg_replace('/%p/', $paypass, $ab['ibadmin.new_client_text_sms']);
            $text_sms = preg_replace('/%l/', $login, $text_sms);

            if ($abs_id) {
                $params = array();
                $params['abs_id'] = $abs_id;
                $params['ctype'] = ab_secure($_POST['f_type_client']);
                $params['uid'] = $QID;
                $params['is_owner'] = 1;
                $params['owner_uid'] = $QID;
                $params['prefs'] = 0;
                ibadmin_DB::_set_uid_absid($params);
                ibadmin_DB::_update_uid_absid($abs_id, $QID);
                ibadmin_DB::_update_accounts_owner_uid($abs_id, $QID);
            }


            if (_send_sms($phone, $text_sms)) {
                write_log($QID, 'sendNewLogin', 1);
                write_log($QID, 'sendNewPayPassword', 1);
            } else {
                write_log($QID, 'sendNewLogin', 0);
                write_log($QID, 'sendNewPayPassword', 0);
            }

            /*

            if (_send_sms($phone, $text_login)) {
                write_log($QID, 'sendNewLogin', 1);
            } else {
                write_log($QID, 'sendNewLogin', 0);
            }
             error_log('login ' . $text_login);
            */


        } else {
            throw new UserException($ab['ibadmin.bank_authError']);
        }

    } catch (UserException $e) {
        write_log($QID, $mess_create_log, 0);
        error_log('write_new_client: ' . $e->getMessage());
        echo ab_result(0, array('message' => $e->getMessage()));
    } catch (SystemException $e) {
        write_log($QID, $mess_create_log, 0);
        error_log(__METHOD__ . $e->getMessage());
    }

}

/**
 * специфичная процедура для подключения физика
 * @throws UserException
 */
function new_fizclient()
{
    global $ab, $ab_user;
    /**
     * abs2doc8
     * 8989898989
     * abs2fio
     * Пупкин Аван Ивонович
     * abs2phone
     * 8989898989
     * abs2rez
     * 1
     */
    try {
        if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

            if (!$ab_user['id']) {
                throw new UserException($ab['ibadmin.bank_authError']);
            }

            try {

                $phone = _prepare_phone($_POST['abs2phone']);
                _check_client_by_phone($phone);

            } catch (UserException $e) {

                $r = _compare_parametr_fio_with_fiodbo($phone, $_POST['abs2fio']);

                $uid_dbo = $r['f_uid'];

                if (!$uid_dbo) {// фамилии не совпали - считаем, что клиент другой
                    throw new UserException($e->getMessage());
                }

                if (_check_oper_is_client($uid_dbo)) {

                    if ((int)$r['f_type_client'] === (int)Local::MAN2_ABS) { //клиент из базы физиков
                        $mes = $ab['ibadmin.isfizclientdbo'];
                        $mes = preg_replace('/\%fio/', ab_secure($_POST['abs2fio']), $mes);
                        throw new UserException($mes);
                    }

                    $res = _create_fiz_data_from_abs($phone);

                    $params1['abs_id'] = $res['abs_id'];
                    $params1['ctype'] = Local::MAN2_ABS;
                    $params1['is_owner'] = 1;
                    $params1['prefs'] = 0;
                    $params1['uid'] = $uid_dbo;
                    $params1['owner_uid'] = $uid_dbo;

                    ibadmin_DB::_set_uid_absid($params1);

                    write_log($uid_dbo, 'ClientJoinFizAbs', 1, $params1);
                    echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

                    //echo ab_result(1, array('message' => 'Ветка присоединения счетов, в случае, если клиент не из базы физиков'));
                    exit();
                } else {
                    //объединяем профиль не клиента с клиентом
                    $res = _create_fiz_data_from_abs($phone);
                    $abs_id = $res['abs_id'];
                    $res['f_type_client'] = Local::MAN2_ABS;

                    if (!trim($res['f_email'])) {
                        unset($res['f_email']);
                    }

                    $oper_id = $ab_user['id'];
                    $res['f_muser'] = $oper_id; // оператор, измняющий запись
                    $res['f_mtim'] = date('Y-m-d H:i:s');

                    unset($res['sex']);
                    unset($res['abs_id']);
                    unset($res['fio']);

                    _prepare_fio($res, $res['f_lastname'], $res['f_name'], $res['f_fname']);
                    _prepare_adress_data($res);
                    _prepare_adress_to_base64($res);

                    _translate_adress_data($res);
                    _check_absid_in_base($abs_id);
//проверить дату рождения
                    _update_owner_operator($uid_dbo, $abs_id, $res);

                    write_log($uid_dbo, 'nonClientJoinFizAbs', 1);
                    echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));
                    exit();
                }
            }

            $res = _create_fiz_data_from_abs($phone);
            _create_operator_data_for_fizclient($res);
            //file_put_contents('/tmp/err.txt', print_r($_POST, 1), FILE_APPEND);
            write_new_client(2);

        } else {
            throw new UserException($ab['ibadmin.bank_authError']);
        }

    } catch (UserException $e) {
        write_log(0, 'createNewFizClient', 0);
        error_log('write_new_fizclient: ' . $e->getMessage());
        echo ab_result(0, array('message' => $e->getMessage()));
    } catch (SystemException $e) {
        write_log(0, 'createNewFizClient', 0);
        error_log(__METHOD__ . $e->getMessage());
    }
}

function getfizclientinfo()
{
    global $ab, $ab_user;

    try {
        if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

            if (!$ab_user['id']) {
                throw new UserException($ab['ibadmin.bank_authError']);
            }

            $phone = _prepare_phone($_POST['abs2phone']);
            $res = _create_fiz_data_from_abs($phone);

            if (!isset($res['abs_id'])) {
                throw new UserException($ab['ibadmin.bank_error.abs_client_notfind']);
            }

            $passport_data = ab_secure($_POST['abs2doc8']);
            $passport_data = substr($passport_data, 0, 2) . ' ' . substr($passport_data, 2, 2) . ' ' . substr($passport_data, 4);

            $data = array('absId' => $res['abs_id'], 'absFull' => $res['abs_id'] . ' ' . $res['fio'] . ' ' . $passport_data);
            echo ab_result(1, array('data' => $data));

        } else {
            throw new UserException($ab['ibadmin.bank_authError']);
        }

    } catch (UserException $e) {
        write_log(0, 'addAccessAccountRbs', 0);
        error_log(__METHOD__ . $e->getMessage());
        echo ab_result(0, array('message' => $e->getMessage()));
    } catch (SystemException $e) {
        write_log(0, 'addAccessAccountRbs', 0);
        error_log(__METHOD__ . $e->getMessage());
    }

}

function _create_fiz_data_from_abs($phone)
{
    global $ab;
    //find client data from abs
    $params = array(ab_secure($_POST['abs2fio']),
        (int)$_POST['abs2rez'],
        8,//todo
        ab_secure($_POST['abs2doc8'])
    );

    $res = ibadmin_abs::_search_client2($params);

    $res['f_ownerbd'] = _prepare_ownerbd($res['f_ownerbd']);

    $res['f_cellphone'] = $phone;

    $fio = $res['fio'];
    $t = preg_split('/\s+/', $fio);

    if (count($t) < 3) {
        throw new UserException($ab['ibadmin.bank_error.fio_abs_empty']);
    }

    $lastname = array_shift($t);
    $name = array_shift($t);
    $fname = implode(' ', $t);

    $res['f_lastname'] = $lastname;
    $res['f_name'] = $name;
    $res['f_fname'] = $fname;

    _prepare_adress_data($res);

    return $res;
}

function _compare_parametr_fio_with_fiodbo($phone, $fio)
{
    $res = ibadmin_DB::_get_oper_by_phone($phone);

    $fio_dbo = base64_decode($res['f_full_name']);
    $fio_dbo = mb_strtoupper(preg_replace('/\s+/', ' ', trim($fio_dbo)));
    $fio = mb_strtoupper(preg_replace('/\s+/', ' ', trim($fio)));


    if ($fio !== $fio_dbo) {
        return false;
    }

    return $res;

}

function _check_oper_is_client($uid)
{
    $abs_id = ibadmin_DB::_get_owners_absid_by_uid($uid);

    if ($abs_id) {
        return true;
    }

    return false;
}

function _check_client_by_phone($phone, $uid = 0)
{
    global $ab;

    $r = ibadmin_DB::_check_client_phone($phone);

    foreach ($r as $i => &$item) {
        if ((int)$item['f_uid'] === (int)$uid) {
            unset($r[$i]);
        }
    }

    if ($r) {
        $mes = $ab['ibadmin.admin_error.createUser.phone'];
        $mes = preg_replace('/\%tel/', ab_secure($phone), $mes);
        throw new UserException($mes);
    }

}

function _check_client_personal_data($params, $uid = 0)
{
    global $ab;

    $info = $params['info'];
    unset($params['info']);

    $r = ibadmin_DB::_check_client_personal_data($params);

    foreach ($r as $i => &$item) {
        if ((int)$item['f_uid'] === (int)$uid) {
            unset($r[$i]);
        }
    }

    if ($r) {
        $mes = $ab['ibadmin.admin_error.createUser.personal_data'];
        $mes = preg_replace('/\%fio/', $info, $mes);
        throw new UserException($mes);
    }

}

/**
 * @param $lastname
 * @param $name
 * @param $fname
 * @param $user_bd
 * @param $info для вывода сообщения об ошибке
 * @param int $uid
 * @throws SystemException
 * @throws UserException
 */
function _check_client_personal_data_new($lastname, $name, $fname, $user_bd, $info, $uid = 0)
{
    global $ab;

    $r = ibadmin_DB::_check_client_personal_data_new($lastname, $name, $fname, $user_bd);

    if ($uid) {
        foreach ($r as $i => &$item) {
            if ((int)$item['f_uid'] === (int)$uid) {
                unset($r[$i]);
            }
        }
    }

    if ($r) {
        $mes = $ab['ibadmin.admin_error.createUser.personal_data'];
        $mes = preg_replace('/\%fio/', $info, $mes);
        throw new UserException($mes);
    }

}

/**
 * редактирование не клиента банка
 */
function save_non_client()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

        try {

            $params = array();

            $params['f_cellphone'] = _prepare_phone($_POST['cellphone']);
            $uid = (int)$_POST['uid'];
            _check_client_by_phone($params['f_cellphone'], $uid);

            $params['f_ownerbd'] = _prepare_ownerbd($_POST['userbd'], 1);
            $email = _prepare_email($_POST['email']);
            $params['f_email'] = base64_encode($email);

            $lastname = ab_secure($_POST['family']);
            $name = ab_secure($_POST['name']);
            $fname = ab_secure($_POST['father']);

            _prepare_fio($params, $lastname, $name, $fname);

            $info = $lastname . ' ' . $name . ' ' . $fname . ' ' . $params['f_ownerbd'];

            $pers_data = array(
                'f_lastname' => $params['f_lastname'],
                'f_name' => $params['f_name'],
                'f_fname' => $params['f_fname'],
                'f_ownerbd' => $params['f_ownerbd'],
                'info' => $info
            );

            //_check_client_personal_data_new($lastname, $name, $fname, $params['f_ownerbd'], $info, $uid);
            _check_client_personal_data($pers_data, $uid);

            ibadmin_DB::_update_operator($uid, $params);

            write_log($uid, 'saveNonClient', 1);
            echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

        } catch (UserException $e) {
            write_log($uid, 'saveNonClient', 0);
            error_log(__METHOD__ . $e->getMessage());
            echo ab_result(0, array('message' => $e->getMessage()));

        } catch (SystemException $e) {
            write_log($uid, 'saveNonClient', 0);
            error_log(__METHOD__ . $e->getMessage());
        }
    }
}

function _prepare_fio(&$params, $lastname, $name, $fname)
{
    _check_fio($lastname, $name, $fname);

    $lastnameEng = translate($lastname);
    $nameEng = translate($name);
    $fnameEng = translate($fname);

    $full_name = implode(' ', array($lastname, $name, $fname));
    $full_Engname = implode(' ', array($lastnameEng, $nameEng, $fnameEng));

    $params['f_lastname'] = base64_encode($lastname);
    $params['f_name'] = base64_encode($name);
    $params['f_fname'] = base64_encode($fname);
    $params['f_Englastname'] = base64_encode($lastnameEng);
    $params['f_Engname'] = base64_encode($nameEng);
    $params['f_Engfname'] = base64_encode($fnameEng);
    $params['f_full_name'] = base64_encode($full_name);
    $params['f_full_Engname'] = base64_encode($full_Engname);
}

/**
 * обновление данных из абс
 */
function refresh_from_abs()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

        try {

            $uid = (int)$_POST['uid'];
            $abs_id = _get_owners_absid_by_uid($uid);

            $data = _get_client($abs_id, 1);

            //клиент не может быть юриком
            $fir_type = Local::FIR_ABS;
            if ($data['f_type_client'] === $fir_type || $data['f_type_client'] === "$fir_type") {
                throw new UserException($ab['ibadmin.bank_error.oper_yur']);
            }

            $fio = isset($data['fio']) ? trim($data['fio']) : '';
            if (!$fio) {
                throw new UserException($ab['ibadmin.bank_error.fio_abs_empty']);
            }
            unset($data['fio']);

            $t = preg_split('/\s+/', $fio);

            if (count($t) < 3) {
                throw new UserException($ab['ibadmin.bank_error.fio_abs_empty']);
            }

            $lastname = array_shift($t);
            $name = array_shift($t);
            $fname = implode(' ', $t);

            _prepare_fio($data, $lastname, $name, $fname);

            try {
                $data['f_cellphone'] = _prepare_phone($data['f_cellphone']);
            } catch (UserException $e) {
                throw new UserException($ab['ibadmin.bank_error.phone_abs']);
            }

            if (trim($data['f_email'])) {
                $data['f_email'] = base64_encode($data['f_email']);
            } else {
                unset($data['f_email']);
            }

            _prepare_adress_data($data);
            _prepare_adress_to_base64($data);

            ibadmin_DB::_update_operator($uid, $data);

            write_log($uid, 'refreshFromAbs', 1);
            echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

        } catch (UserException $e) {
            write_log($uid, 'refreshFromAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
            echo ab_result(0, array('message' => $e->getMessage()));

        } catch (SystemException $e) {
            write_log($uid, 'refreshFromAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
        }

    }

}

/**
 * поиск клиента в АБС по идентификатору клиента
 * @param $abs_id
 * @param int $proc 0 - структура данных как в абс  1- структура данных преобразуется к необходимому формату
 * @return bool
 * @throws UserException
 */
function _get_client($abs_id, $proc = 0)
{
    global $ab;

    $res = ibadmin_abs::_get_client($abs_id, $proc);

    if (!$res) {
        throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
    }

    return isset($res[0]) ? $res[0] : array();
}

function _get_owners_absid_by_uid($uid)
{
    $abs_id = trim(ibadmin_DB::_get_owners_absid_by_uid($uid));

    if (!$abs_id) {
        throw new SystemException(__METHOD__ . " abs_id for user $uid not find");
    }

    return $abs_id;
}

/**
 * объединение не клиента банка с возможным клиентом банка
 */
function non_client_join_abs()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

        try {
            $uid = (int)$_POST['uid'];

            $userbd = _prepare_ownerbd($_POST['userbd'], 1);
            $userbd_or = $userbd . ' 00:00:00'; //для oracle

            $phone = _prepare_phone_for_abs($_POST['cellphone']);

            $r = ibadmin_abs::_search_client_by_phone_and_bd(array($phone, $userbd_or));

            $phone = _prepare_phone($phone);

            if (!$r) {
                throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
            }

            $family = ab_secure(trim($_POST['family']));
            $name = ab_secure(trim($_POST['name']));
            $father = ab_secure(trim($_POST['father']));

            $fio_params = array(
                'family' => $family,
                'name' => $name,
                'father' => $father
            );

            $row_abs = _check_fio_with_fioabs($fio_params, $r);
            unset($row_abs['fio']);


            if (!$row_abs) {
                throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
            }

            //клиент не может быть юриком
            $fir_type = Local::FIR_ABS;
            if ($row_abs['f_type_client'] === $fir_type || $row_abs['f_type_client'] === "$fir_type") {
                throw new UserException($ab['ibadmin.bank_error.oper_yur']);
            }

            _prepare_fio($row_abs, $family, $name, $father);

            $row_abs['f_ownerbd'] = $userbd;
            $row_abs['f_cellphone'] = $phone;

            $abs_id = $row_abs['abs_id'];
            unset($row_abs['abs_id']);

            if (!$abs_id) {
                throw new UserException($ab['ibadmin.bank_error.absid_notfind']);
            }

            _check_absid_in_base($abs_id);

            if (!trim($row_abs['f_email'])) {
                $row_abs['f_email'] = base64_encode(ab_secure($_POST['email']));
            } else {
                $row_abs['f_email'] = base64_encode($row_abs['f_email']);
            }

            _prepare_adress_data($row_abs);
            _prepare_adress_to_base64($row_abs);
            _update_owner_operator($uid, $abs_id, $row_abs);

            write_log($uid, 'nonClientJoinAbs', 1);
            echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

        } catch (UserException $e) {
            write_log($uid, 'nonClientJoinAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
            echo ab_result(0, array('message' => $e->getMessage()));

        } catch (SystemException $e) {
            write_log($uid, 'nonClientJoinAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
        }
    }
}

/**
 * поиск клиента в АБС по идентификатору клиента
 * @param $abs_id
 * @param int $proc 0 - структура данных как в абс  1- структура данных преобразуется к необходимому формату
 * @return bool
 * @throws UserException
 */
/*function _get_client($abs_id, $proc = 0)
{
    global $ab;

    $res = ibadmin_abs::_get_client($abs_id, $proc);

    if (!$res) {
        throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
    }

    return isset($res[0]) ? $res[0] : array();
}*/

/**
 * @param $uid
 * @return string
 * @throws SystemException
 */
/*function _get_owners_absid_by_uid($uid)
{
    $abs_id = trim(ibadmin_DB::_get_owners_absid_by_uid($uid));

    if (!$abs_id) {
        throw new SystemException(__METHOD__ . " abs_id for user $uid not find");
    }

    return $abs_id;
}*/

/**
 * объединение вновь создаваемого физика банка с возможно уже существующим
 */
function new_client_join_abs()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

        try {
            $uid = (int)$_POST['uid'];

            $userbd = _prepare_ownerbd($_POST['userbd'], 1);
            $userbd_or = $userbd . ' 00:00:00'; //для oracle

            $phone = _prepare_phone_for_abs($_POST['cellphone']);

            $r = ibadmin_abs::_search_client_by_phone_and_bd(array($phone, $userbd_or));

            $phone = _prepare_phone($phone);

            if (!$r) {
                throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
            }

            $family = ab_secure(trim($_POST['family']));
            $name = ab_secure(trim($_POST['name']));
            $father = ab_secure(trim($_POST['father']));

            $fio_params = array(
                'family' => $family,
                'name' => $name,
                'father' => $father
            );

            $row_abs = _check_fio_with_fioabs($fio_params, $r);
            unset($row_abs['fio']);


            if (!$row_abs) {
                throw new UserException($ab['ibadmin.bank_error.user_abs_notfind']);
            }

            //клиент не может быть юриком
            $fir_type = Local::FIR_ABS;
            if ($row_abs['f_type_client'] === $fir_type || $row_abs['f_type_client'] === "$fir_type") {
                throw new UserException($ab['ibadmin.bank_error.oper_yur']);
            }

            _prepare_fio($row_abs, $family, $name, $father);

            $row_abs['f_ownerbd'] = $userbd;
            $row_abs['f_cellphone'] = $phone;

            $abs_id = $row_abs['abs_id'];
            unset($row_abs['abs_id']);

            if (!$abs_id) {
                throw new UserException($ab['ibadmin.bank_error.absid_notfind']);
            }

            _check_absid_in_base($abs_id);

            if (!trim($row_abs['f_email'])) {
                $row_abs['f_email'] = base64_encode(ab_secure($_POST['email']));
            } else {
                $row_abs['f_email'] = base64_encode($row_abs['f_email']);
            }

            _prepare_adress_data($row_abs);
            _prepare_adress_to_base64($row_abs);
            _update_owner_operator($uid, $abs_id, $row_abs);

            write_log($uid, 'nonClientJoinAbs', 1);
            echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

        } catch (UserException $e) {
            write_log($uid, 'nonClientJoinAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
            echo ab_result(0, array('message' => $e->getMessage()));

        } catch (SystemException $e) {
            write_log($uid, 'nonClientJoinAbs', 0);
            error_log(__METHOD__ . $e->getMessage());
        }
    }
}

/**
 * @param $row_abs
 */
function _prepare_adress_data(&$row_abs)
{
    if ($row_abs['f_registration_loc']) {
        $row_abs['f_registration_city'] = $row_abs['f_registration_loc'] . ' ' . $row_abs['f_registration_city'];
    }
    unset($row_abs['f_registration_loc']);

    if ($row_abs['f_registration_tifr']) {
        $row_abs['f_registration_street'] = $row_abs['f_registration_tifr'] . ' ' . $row_abs['f_registration_street'];
    }
    unset($row_abs['f_registration_tifr']);

    if ($row_abs['f_habitation_loc']) {
        $row_abs['f_habitation_city'] = $row_abs['f_habitation_loc'] . ' ' . $row_abs['f_habitation_city'];
    }
    unset($row_abs['f_habitation_loc']);

    if ($row_abs['f_habitation_tifr']) {
        $row_abs['f_habitation_street'] = $row_abs['f_habitation_tifr'] . ' ' . $row_abs['f_habitation_street'];
    }
    unset($row_abs['f_habitation_tifr']);

}

/**
 * call after _prepare_adress_data
 * @param $row_abs
 */
function _translate_adress_data(&$row_abs)
{
    $keys = array('f_registration_obl' => 'f_registrationEng_obl', 'f_registration_city' => 'f_registrationEng_city',
        'f_registration_street' => 'f_registrationEng_street', 'f_registration_home' => 'f_registrationEng_home',
        'f_registration_corp' => 'f_registrationEng_corp', 'f_registration_app' => 'f_registrationEng_app',
        'f_registration_index' => 'f_registrationEng_index',
        'f_habitation_obl' => 'f_habitationEng_obl', 'f_habitation_city' => 'f_habitationEng_city',
        'f_habitation_street' => 'f_habitationEng_street', 'f_habitation_home' => 'f_habitationEng_home',
        'f_habitation_corp' => 'f_habitationEng_corp', 'f_habitation_app' => 'f_habitationEng_app',
        'f_habitation_index' => 'f_habitationEng_index',

    );

    foreach ($keys as $key => $keyEng) {
        $row_abs[$key] = translate($row_abs[$key]);
    }

}

/**
 * @param $row_abs for update
 */
function _prepare_adress_to_base64(&$row_abs)
{
    $keys = array('f_registration_obl', 'f_registration_city', 'f_registration_street', 'f_registration_home',
        'f_registration_corp', 'f_registration_app', 'f_registration_index',
        'f_registrationEng_obl', 'f_registrationEng_city', 'f_registrationEng_street', 'f_registrationEng_home',
        'f_registrationEng_corp', 'f_registrationEng_app', 'f_registrationEng_index',
        'f_habitation_obl', 'f_habitation_city', 'f_habitation_street', 'f_habitation_home',
        'f_habitation_corp', 'f_habitation_app', 'f_habitation_index',
        'f_habitationEng_obl', 'f_habitationEng_city', 'f_habitationEng_street', 'f_habitationEng_home', 'f_habitationEng_corp',
        'f_habitationEng_app', 'f_habitationEng_index'
    );

    foreach ($keys as $key) {
        $row_abs[$key] = base64_encode($row_abs[$key]);
    }
}

//todo

/**
 * проверяем, нет ли собственника с таким abs_id
 * @param $abs_id
 */
function _check_absid_in_base($abs_id)
{
    global $ab;

    if (ibadmin_DB::_check_client_by_absid($abs_id)) {

        $uid = ibadmin_DB::get_owner_uid_by_absid($abs_id);
        $fio = ibadmin_DB::_get_users_fio($uid);
        $mes = $ab['ibadmin.bank_error.find_equal_absid'];
        $mes = preg_replace('/\%fio/', $fio, $mes);
        $mes = preg_replace('/\%absid/', $abs_id, $mes);

        throw new UserException($mes);
    }

}

function _update_owner_uid_for_absid($owner_uid, $abs_id)
{
    ibadmin_DB::_update_accounts_owner_uid($abs_id, $owner_uid);
    ibadmin_DB::_update_uid_absid($abs_id, $owner_uid);
}

function _update_owner_operator($uid, $abs_id, $row_abs)
{
    ibadmin_DB::_update_operator($uid, $row_abs);

    $params = array();
    $params['abs_id'] = $abs_id;
    $params['ctype'] = ab_secure($row_abs['f_type_client']);
    $params['uid'] = $uid;
    $params['is_owner'] = 1;
    $params['owner_uid'] = $uid;
    $params['prefs'] = 0;

    ibadmin_DB::_set_uid_absid($params); //добавляем новую запись для собственника
    ibadmin_DB::_delete_accounts_by_uid_absid($uid, $abs_id); // удаляем счета из присоединенных
    _update_owner_uid_for_absid($uid, $abs_id);

}


/**
 * @param $fio_params
 * @param $data_abs
 * @return bool
 */
function _check_fio_with_fioabs($fio_params, $data_abs)
{
    $fio = mb_strtoupper($fio_params['family']) . ' ' . mb_strtoupper($fio_params['name']) . ' ' . mb_strtoupper($fio_params['father']);

    foreach ($data_abs as $item_abs) {
        $fio_abs = trim($item_abs['fio']);

        if (!$fio_abs) {
            continue;
        }

        $fio_abs = mb_strtoupper(preg_replace('/\s+/', ' ', $fio_abs));

        if ($fio_abs === $fio) {
            return $item_abs;
        }
    }

    return false;

}

/**
 * @param $phone
 */
function _prepare_phone_for_abs($phone)
{

    $phone = _prepare_phone($phone);
    $t = substr($phone, 1, 3);
    $phone = substr_replace($phone, '(' . $t . ')', 1, 3);
    $phone = preg_replace('/^8/', '+7', $phone);

    return $phone;

}

function _prepare_phone($phone)
{
    global $ab;

    $phone = preg_replace(array('/-/', '/\+7/', '/\(/', '/\)/', '/\s+/'), array('', '8', '', '', ''), trim($phone));

    if (strlen($phone) == 10) {
        $phone = '8' . $phone;
    }

    $phone = preg_replace('/^[0-9]{1}/', '8', $phone);

    if (!preg_match('/^[0-9]{11,20}$/', $phone)) {
        throw new UserException($ab['ibadmin.bank_error.phone']);
    }

    return $phone;
}


function _prepare_email($email)
{
    global $ab;

    if (!trim($email)) {
        throw new UserException($ab['ibadmin.bank_error.email']);
    }

    return $email;
}

function _check_fio($lastname, $name, $fname)
{
    global $ab;

    if (!$lastname) {
        throw new UserException($ab['ibadmin.bank_error.family']);
    }

    if (!$name) {
        throw new UserException($ab['ibadmin.bank_error.name']);
    }

    if (!$fname) {
        throw new UserException($ab['ibadmin.bank_error.father']);
    }

}

function _prepare_ownerbd($date, $req = 0)
{
    global $ab;

    if ($req) {
        if (!trim($date)) {
            throw new UserException($ab['ibadmin.bank_error.userbd']);
        }

        $oDt = DateTime::createFromFormat('d.m.Y', trim($date));
        if (!is_object($oDt)) {
            $oDt = DateTime::createFromFormat('Y-m-d', trim($date));
        }

        if (!is_object($oDt)) {
            throw new UserException($ab['ibadmin.bank_error.userbd']);
        }

        return $oDt->format('Y-m-d');

    } else {
        if (!trim($date)) {
            return $date;
        }

        $oDt = DateTime::createFromFormat('d.m.Y', trim($date));
        if (!is_object($oDt)) {
            return $date;
        }
        return $oDt->format('Y-m-d');
    }

}

function _send_sms($phone, $text)
{
    if (!$text or !$text)
        return false;

    $sms = new SMS();
    $sms->send($phone, $text);

    return true;
}


function get_accounts()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_') || ab_rights('ibadmin_account_')) {

        $abs_id = ab_secure($_POST['absid']);

        //$token_need = ibadmin_DB::_get_token_need($abs_id);

        if (isset($_GET['abs']) && (int)$_GET['abs'] == 2) {
            $accs = ibadmin_abs::_accounts_list_fiz2($abs_id);
        } else {
            $accs = ibadmin_abs::_accounts_list($abs_id);
        }

        echo ab_result(1, array('data' => $accs));

    } else
        echo ab_result(0, array('message' => $ab['ibadmin.bank_authError']));

}


function get_accounts_join()
{
    global $ab;
    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_') || ab_rights('ibadmin_account_')) {
        $uid = (int)$_POST['uid'];

        $res = ibadmin_DB::_get_accounts_join($uid);
        if ($res) {
            echo ab_result(1, array('data' => $res));

        } else
            echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['ibadmin.bank_authError']));

}

/**
 * список всех счетов клиента
 */
function get_accounts_all()
{
    global $ab;
    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_') || ab_rights('ibadmin_account_')) {
        try {

            $uid = (int)$_REQUEST['uid'];

            $abs_ids = _get_abs_ids_byprefs($uid, 'allacc');

            $res = array();

            foreach ($abs_ids as $item) {
                if ((int)$item['ctype'] === (int)Local::MAN2_ABS) {
                    $accs = ibadmin_abs::_accounts_list_fiz($item['abs_id']);
                } else {
                    $accs = ibadmin_abs::_accounts_list($item['abs_id'], $prepare = 1);
                }

                $accs = _prepareAccAll($accs, $uid);
                $res = array_merge($res, $accs);
            }

            $access_acc = ibadmin_DB::_get_accounts_join($uid);
            $res_acc = _prepare_access_acc($access_acc, $uid);

            if ($res_acc) {
                $res = array_merge($res, $res_acc);
            }

            echo ab_result(1, array('data' => $res));

        } catch (UserException $e) {
            echo ab_result(0, array('message' => $e->getMessage()));
        } catch (SystemException $e) {
            error_log(__METHOD__ . $e->getMessage());
        }

    } else
        echo ab_result(0, array('message' => $ab['ibadmin.bank_authError']));

}

function _get_user_fio($uid, $absid = '')
{
    $fio = ibadmin_DB::_get_users_fio($uid);

    if (!$fio && $absid) {

        $cl = ibadmin_abs::_get_client($absid);
        $fio = $cl[0]['CCUSNAME'];

    }

    return $fio;
}

/**
 * @param $abs_data
 * @param $owner_uid
 * @return array
 * @throws SystemException
 */
function _prepareAccAll($abs_data, $owner_uid)
{
    $res = array();
    $acc_types = getAccTypes();
    if (!$owner_uid) {
        throw  new SystemException(__METHOD___ . ' owner_uid is empty ' . print_r($abs_data, 1));
    }
    $fio = _get_user_fio($owner_uid);
    foreach ($abs_data as $key => $acc_data) {
        $a = array();
        $a['f_account'] = $acc_data['account'];
        $a['f_fio'] = $fio;
        $acc_type = isset($acc_types[$acc_data['accType']]) ?
            $acc_types[$acc_data['accType']] : (trim($acc_data['accType']) ? $acc_data['accType'] : Local::ACCTYPE_NOTDEF);
        $a['f_accType'] = $acc_type;
        $a['f_dateStart'] = $acc_data['dt_start'];
        $a['f_dateEnd'] = ''; //owner
        $a['f_sum'] = floatval($acc_data['sum']);
        $a['f_create'] = 1;
        $a['f_pay'] = 1;
        $a['f_isowner'] = 1;
        $a['f_limit1'] = 0;
        $a['f_limit30'] = 0;
        $a['f_limit90'] = 0;
        $a['f_limit360'] = 0;

        $a['abs'] = 1; //счета из абс

        $res[] = $a;
    }

    return $res;

}

function getAccTypes()
{
    global $ab;

    $acctypes_abs_all = $ab['ibadmin.bank_acctypes.abs_all'];

    $acctypes = preg_split('/\s*,\s*/', $acctypes_abs_all);

    $r = array();

    foreach ($acctypes as $acctype) {
        $t = explode('_', $acctype);
        $r[$t[0]] = $t[1];
    }

    return $r;
}

/**
 * @param $db_data
 * @param $uid
 * @return array
 * @throws SystemException
 */
function _prepare_access_acc($db_data, $uid)
{
    if (!is_array($db_data)) {

    }
    if (!$db_data) {
        return $db_data;
    }

    $res = array();

    $abs_ids_data = _get_abs_ids_data($uid);

    foreach ($db_data as $item) {
        $a = array();

        $abs_id = $item['f_abs_id'];
        if (!$abs_id) {
            throw new SystemException(__METHOD__ . " Abs_id for user $uid, acc -{$item['f_account']} is empty.");
        }

        $account = $item['f_account'];
        $owner_uid = $abs_ids_data[$abs_id]['owner_uid'];

        $acc_types = getAccTypes();

        if ((int)$item['f_accType'] === 9) {
            $fio = base64_decode($item['f_name']);
            $abs_data = ibadmin_abs::_account_fiz($abs_id, $account); // нужен актуальный остаток
            $a['f_accType'] = $abs_data[0]['accType'];
        } else {
            $fio = _get_user_fio($owner_uid, $abs_id);
            $abs_data = ibadmin_abs::_account($abs_id, $account); // нужен актуальный остаток
            $a['f_accType'] = isset($acc_types[$abs_data[0]['accType']]) ? $acc_types[$abs_data[0]['accType']] : Local::ACCTYPE_NOTDEF;
        }

        $a['f_account'] = $account;
        $a['f_fio'] = $fio;





        $a['f_dateStart'] = $abs_data[0]['dt_start'];
        $a['f_dateEnd'] = $item['f_proxy_date'];
        $a['f_sum'] = $abs_data[0]['sum'];

        $a['f_create'] = $item['f_create'];
        $a['f_pay'] = $item['f_pay'];
        $a['f_isowner'] = $item['f_is_owner'];
        $a['f_limit1'] = $item['f_limit1'];
        $a['f_limit30'] = $item['f_limit30'];
        $a['f_limit90'] = $item['f_limit90'];
        $a['f_limit360'] = $item['f_limit360'];

        $res[] = $a;

    }
    return $res;
}


/**
 * @param $uid
 * @param $type - 0 - запросить список всех счетов клиента абс; 1- только из таблицы t_ib_accounts
 * @return array
 */
function _get_abs_ids_byprefs($uid, $type)
{
    $uid = (int)$uid;
    $abs_ids = ibadmin_DB::_get_uid_absids($uid);

    $res = array();

    switch ($type) {
        case 'allacc':
            foreach ($abs_ids as $abs_id => $item) {
                if ($item['prefs'] === 0 || $item['prefs'] === '0') {
                    $res[] = array('abs_id' => $abs_id, 'ctype' => $item['ctype']);
                }
            }
            break;

        case 'accessacc':
            foreach ($abs_ids as $abs_id => $item) {
                if ($item['prefs'] === 1 || $item['prefs'] === '1') {
                    $res[] = array('abs_id' => $abs_id, 'ctype' => $item['ctype']);
                }
            }
            break;

        default:
            break;
    }

    return $res;
}

/**
 * @param $uid
 * @return array
 */
function _get_abs_ids_data($uid)
{
    $uid = (int)$uid;
    $abs_ids = ibadmin_DB::_get_uid_absids($uid);

    $res = array();

    foreach ($abs_ids as $abs_id => $item) {
        $res[$item['abs_id']] = array('owner_uid' => $item['owner_uid'],
            'ctype' => $item['ctype'],
            'is_owner' => $item['is_owner'],
            'prefs' => $item['prefs']);
    }

    return $res;
}


/**
 * @param bool $join_with_owner_fiz - пока присоединяем только счета физиков -владельцев
 */
function join_acc()
{
    global $ab_user, $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_') || ab_rights('ibadmin_account_')) {

        try {

            if (!$ab_user['id']) {
                throw new SystemException("Operators Uid not find!");
            }

            $oper_id = $ab_user['id'];

            $data = array();


            foreach ($_POST as $key => $val) {
                if (preg_match('/^f_owner_/', $key)) {

                    if ($val === '1' || $val === 1) {
                        $acc = preg_replace('/f_owner_/', '', $key);
                        if (!isset($data[$acc])) {
                            $data[$acc] = array();
                        }
                        $data[$acc]['owner'] = $val;
                        $data[$acc]['pay'] = $val;
                        $data[$acc]['create'] = $val;
                        $data[$acc]['view'] = $val;
                    }

                }

                if (preg_match('/^f_pay_/', $key)) {
                    if ($val === '1' || $val === 1) {
                        $acc = preg_replace('/f_pay_/', '', $key);
                        if (!isset($data[$acc])) {
                            $data[$acc] = array();
                        }
                        $data[$acc]['pay'] = $val;
                        $data[$acc]['create'] = $val;
                        $data[$acc]['view'] = $val;
                    }
                }

                if (preg_match('/^f_create_/', $key)) {
                    if ($val === '1' || $val === 1) {
                        $acc = preg_replace('/f_create_/', '', $key);
                        if (!isset($data[$acc])) {
                            $data[$acc] = array();
                        }
                        $data[$acc]['create'] = $val;
                        $data[$acc]['view'] = $val;
                    }
                }

                if (preg_match('/^f_view_/', $key)) {
                    $acc = preg_replace('/f_view_/', '', $key);
                    if ($val === '1' || $val === 1) {
                        if (!isset($data[$acc])) {
                            $data[$acc] = array();
                        }
                        $data[$acc]['view'] = $val;
                    }
                }
            }

            $uid = (int)$_POST['f_uid'];
            $absid = ab_secure($_POST['absid_from']);
            $join_accounts = ibadmin_DB::_get_accounts_by_uid_absid($uid, $absid);

            if ($data) {
                if (!$uid) {
                    throw  new SystemException(__METHOD__ . " $uid is undefined.");
                }

                $owners_uid = ibadmin_DB::_get_owner_by_absid($absid);
                if (in_array($uid, $owners_uid)) {
                    $params = array('f_user_uid' => $uid,
                        'f_account' => $acc);
                    throw new UserException($ab['ibadmin.admin_error.addUser']);
                }

                $proxy_date = ab_secure($_POST['proxydate']);

                $owner_uid = ibadmin_DB::get_owner_uid_by_absid($absid);


                foreach ($data as $acc => $dd) {

                    if (isset($_POST['f_proxydate_' . $acc]) && $_POST['f_proxydate_' . $acc]) {
                        $proxy_date = ab_secure($_POST['f_proxydate_' . $acc]);
                    }

                    $f_limit1 = 0.00;
                    if (isset($_POST['f_limit1_' . $acc])) {
                        $f_limit1 = floatval($_POST['f_limit1_' . $acc]);
                    }

                    $f_limit30 = 0.00;
                    if (isset($_POST['f_limit30_' . $acc])) {
                        $f_limit30 = floatval($_POST['f_limit30_' . $acc]);
                    }

                    $f_limit90 = 0.00;
                    if (isset($_POST['f_limit90_' . $acc])) {
                        $f_limit90 = floatval($_POST['f_limit90_' . $acc]);
                    }

                    $f_limit360 = 0.00;
                    if (isset($_POST['f_limit360_' . $acc])) {
                        $f_limit360 = floatval($_POST['f_limit360_' . $acc]);
                    }

                    if ($f_limit30 && ($f_limit30 < $f_limit1)) {
                        throw new UserException($ab['ibadmin.admin_error.addLimit']);
                    }

                    if ($f_limit90 && ($f_limit90 < $f_limit30)) {
                        throw new UserException($ab['ibadmin.admin_error.addLimit']);
                    }

                    if ($f_limit360 && ($f_limit360 < $f_limit90)) {
                        throw new UserException($ab['ibadmin.admin_error.addLimit']);
                    }

                    if ($f_limit360) {
                        if ($f_limit1 === 0.00 || $f_limit30 === 0.00 || $f_limit90 === 0.00) {
                            throw new UserException($ab['ibadmin.admin_error.addLimit']);
                        }
                    }

                    if ($f_limit90) {
                        if ($f_limit1 === 0.00 || $f_limit30 === 0.00) {
                            throw new UserException($ab['ibadmin.admin_error.addLimit']);
                        }
                    }

                    if ($f_limit30) {
                        if ($f_limit1 === 0.00) {
                            throw new UserException($ab['ibadmin.admin_error.addLimit']);
                        }
                    }

                    $params = array(

                        'f_user_uid' => $uid,
                        'f_account' => $acc,
                        'f_view' => isset($dd['view']) ? $dd['view'] : 0,
                        'f_create' => isset($dd['create']) ? $dd['create'] : 0,
                        'f_pay' => isset($dd['pay']) ? $dd['pay'] : 0,
                        'f_limit1' => $f_limit1,
                        'f_limit30' => $f_limit30,
                        'f_limit90' => $f_limit90,
                        'f_limit360' => $f_limit360,
                        'f_is_owner' => isset($dd['owner']) ? $dd['owner'] : 0,
                        'f_accType' => isset($dd['acc_type']) ? $dd['acc_type'] : '',
                        'f_name' => isset($dd['name']) ? $dd['name'] : '',
                        'f_proxy_date' => $proxy_date,
                        'f_abs_id' => $absid,
                        'f_curr' => isset($dd['kvl']) ? $dd['kvl'] : '',
                        'f_cardBegin' => isset($dd['cardBegin']) ? $dd['cardBegin'] : '',
                        'f_cardEnd' => isset($dd['cardEnd']) ? $dd['cardEnd'] : '',
                        'f_cardStat' => isset($dd['cardstat']) ? $dd['cardstat'] : '',
                        'f_owner_uid' => $owner_uid

                    );

                    $accaccessnotify = new accaccessnotify();
                    $accaccessnotify->send($acc, $uid, $dd['view'], $dd['create'], $dd['pay'], $dd['owner']);

                    if (isset($_GET['abs']) && (int)$_GET['abs'] == 2) {
                        $abs2 = 1;
                        $params['f_name'] = base64_encode(trim(preg_replace('/[0-9]+/', '', $_POST['f_abs_name'])));
                        $params['f_accType'] = cType::MAN2;
                    }

                    $params['f_user'] = $oper_id; // оператор, добавивший запись
                    $params['f_muser'] = $oper_id; // оператор, измняющий запись
                    $params['f_tim'] = date('Y-m-d H:i:s');
                    $params['f_mtim'] = date('Y-m-d H:i:s');


                    if (isset($join_accounts[$acc])) {
                        unset($join_accounts[$acc]);
                    }

                    ibadmin_DB::set_access_account($params);

                    $dt_add = date('Y-m-d H:i:s');
                    _add_to_change_history('accessAdd', $acc, $uid, $params, $dt_add);
                    _add_to_history($acc, $absid, $dt_add);
                }

                if ($join_accounts) {
                    foreach ($join_accounts as $acc) {
                        ibadmin_DB::delete_from_access($uid, $acc);
                    }
                }

                /*  if (isset($_POST['tokenNeed']) && $_POST['tokenNeed']) {
                      $token_params = array();
                      $token_params['f_tim'] = date('Y-m-d');
                      $token_params['f_abs_name'] = $_POST['f_abs_name'];
                      $token_params['f_abs_id'] = $absid;
                      $token_params['f_state'] = 1;
                      $token_params['f_sea'] = ab_f_sea(array($_POST['f_abs_id'], $_POST['f_abs_name']), '');

                      ibadmin_DB::set_user_tokens($uid, $token_params);
                  }*/

                $params1 = array();
                $params1['abs_id'] = $absid;
                $params1['ctype'] = isset($abs2) ? cType::MAN2:cType::MAN;
                $params1['is_owner'] = 0;
                $params1['prefs'] = 1;
                $params1['uid'] = $uid;
                $params1['owner_uid'] = $owner_uid;

                ibadmin_DB::_set_uid_absid($params1);

                write_log($uid, 'addAccessAccount', 1, $params);
                echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

            }

        } catch (UserException $e) {
            write_log($uid, 'addAccessAccount', 0, $params);
            echo ab_result(0, array('message' => $e->getMessage()));
        } catch (SystemException $e) {
            write_log($uid, 'addAccessAccount', 0, $params);
            error_log($e->getMessage());
        }
    } else {
        echo ab_result(0, array('message' => $ab['ibadmin.bank_authError']));

    }
}

/**
 * изменение прав доступа к счету
 */
function account_rights()
{
    global $ab, $ab_user;

    try {
        if (!ab_rights('ibadmin_dbo_admin_') && !ab_rights('ibadmin_dbo_edit_users_') && !ab_rights('ibadmin_account_')) {
            throw new UserException($ab['var.ibadmin.bank_authError']);
        }
        $uid = (int)$_POST['uid'];

        $acc = ab_secure($_POST['account']);
        $dov_date = ab_secure($_POST['edit_rights_date']);

        $create = ab_secure($_POST['edit_rights_create']);
        $pay = ab_secure($_POST['edit_rights_pay']);
        $view = ab_secure($_POST['edit_rights_view']);
        $owner = ab_secure($_POST['edit_rights_owner']);
        $limit1 = floatval($_POST['edit_rights_limit1']);
        $limit30 = floatval($_POST['edit_rights_limit30']);
        $limit90 = floatval($_POST['edit_rights_limit90']);
        $limit360 = floatval($_POST['edit_rights_limit360']);

        $accaccessnotify = new accaccessnotify();
        $accaccessnotify->send($acc, $uid, $view, $create, $pay, $owner);

        if ($limit30 && ($limit30 < $limit1)) {
            throw new UserException($ab['ibadmin.admin_error.addLimit']);
        }

        if ($limit90 && ($limit90 < $limit30)) {
            throw new UserException($ab['ibadmin.admin_error.addLimit']);
        }

        if ($limit360 && ($limit360 < $limit90)) {
            throw new UserException($ab['ibadmin.admin_error.addLimit']);
        }

        if ($limit360) {
            if ($limit1 === 0.00 || $limit30 === 0.00 || $limit90 === 0.00) {
                throw new UserException($ab['ibadmin.admin_error.addLimit']);
            }
        }

        if ($limit90) {
            if ($limit1 === 0.00 || $limit30 === 0.00) {
                throw new UserException($ab['ibadmin.admin_error.addLimit']);
            }
        }

        if ($limit30) {
            if ($limit1 === 0.00) {
                throw new UserException($ab['ibadmin.admin_error.addLimit']);
            }
        }


        if ($owner === 1 || $owner === '1') {
            if (!$dov_date) {
                throw new UserException($ab['ibadmin.access.date_dov.err']);
            }
            $view = $pay = $create = 1;
        }
        if ($pay === 1 || $pay === '1') {
            if (!$dov_date) {
                throw new UserException($ab['ibadmin.access.date_dov.err']);
            }
            $view = $create = 1;
        }
        if ($create === 1 || $create === '1') {
            $view = 1;
        }

        $params = array('f_proxy_date' => $dov_date,
            'f_create' => $create,
            'f_view' => $view,
            'f_pay' => $pay,
            'f_is_owner' => $owner,
            'f_limit1' => $limit1,
            'f_limit30' => $limit30,
            'f_limit90' => $limit90,
            'f_limit360' => $limit360
        );

        $params['f_muser'] = $ab_user['id']; // оператор, измняющий запись
        $params['f_mtim'] = date('Y-m-d H:i:s');

        $dt_change = date('Y-m-d H:i:s');
        if ($view === 0 || $view === '0') {
            $acc_access_data = ibadmin_DB::get_acc_access_data($uid, $acc);
            $abs_id = $acc_access_data['f_abs_id'];
            ibadmin_DB::delete_from_access($uid, $acc);

            $acc_count = ibadmin_DB::get_acc_access_count($uid, $abs_id);

            if ($acc_count === 0 || $acc_count === '0') {
                ibadmin_DB::delete_from_uid_absid($uid, $abs_id);

            }

            ibadmin_DB::delete_acc_from_hash($uid, $acc);

            _add_to_change_history('accessDelete', $acc, $uid, $params, $dt_change);
            _add_to_history($acc, $abs_id, $dt_change);

        } else {

            ibadmin_DB::update_acc_access($uid, $acc, $params);

            $abs_id = ibadmin_DB::get_absid_by_acc($uid, $acc);
            _add_to_change_history('accessSaveChange', $acc, $uid, $params, $dt_change);
            _add_to_history($acc, $abs_id, $dt_change);
        }

        $accaccessnotify = new accaccessnotify();
        $accaccessnotify->send($acc, $user_uid, 1, $can_create, 0, 0);

        write_log($uid, 'changeAccAccess', 1, $params);
        echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));

    } catch (UserException $e) {
        write_log($_POST['uid'], 'changeAccAccess', $params);
        echo ab_result(0, array('message' => $e->getMessage()));
    } catch (SystemException $e) {
        write_log($_POST['uid'], 'changeAccAccess', $params);
        error_log($e->getMessage());
    }

}

/**
 * @param $oper
 * @param $acc
 * @param $user_uid
 * @param array $new_user_data
 * @param array $old_user_data
 * @throws SystemException
 */
function _add_to_change_history($oper, $acc, $user_uid, $new_user_data = array(), $dt = '')
{
    global $ab_user, $ab;

    if (!$ab_user['id']) {
        throw new SystemException("Operators Uid not find!");
    }

    ibadmin_DB::prepare_accesschange_history_table($acc);

    $oper_id = $ab_user['id'];

    $history_params = array();

    $rights = array('f_view', 'f_create', 'f_pay', 'f_is_owner');

    switch ($oper) {

        case 'accessSaveChange':

            foreach ($rights as $right) {
                $history_params[$right] = $new_user_data[$right];
            }

            break;

        case 'accessDelete':

            foreach ($rights as $right) {
                $history_params[$right] = 0;
            }

            break;

        case 'accessAdd':

            foreach ($rights as $right) {
                $history_params[$right] = $new_user_data[$right];
            }

            break;

    }

    $history_params['f_uid'] = $user_uid;
    if ($dt) {
        $history_params['f_tim'] = $dt;
    }

    $history_params['f_limit1'] = $new_user_data['f_limit1'];
    $history_params['f_limit30'] = $new_user_data['f_limit30'];
    $history_params['f_limit90'] = $new_user_data['f_limit90'];
    $history_params['f_limit360'] = $new_user_data['f_limit360'];
    $history_params['f_operation'] = $oper;
    $history_params['f_source'] = 'ibadmin';
    $history_params['f_oper_uid'] = $oper_id;
    $history_params['f_proxy_date'] = $new_user_data['f_proxy_date'];
    $history_params['f_operdoc'] = '';
    $fio = ibadmin_DB::get_user_fio($user_uid);
    $history_params['f_name'] = $fio;
    $history_params['f_icon'] = _get_icon($user_uid);

    $oper_fio = _get_oper_fio($oper_id);
    $oper_fio = preg_replace('/\%fio/', $oper_fio, $ab['ibadmin.accaccess.bank_oper']);
    $history_params['f_oper_name'] = $oper_fio;

    ibadmin_DB::add_to_access_change_history($acc, $history_params);

}

function _get_icon($uid)
{

    $is_icon = ibadmin_DB::check_icon($uid);

    $icon = '';
    if ($is_icon) {
        $icon = _get_ava_path_forfe($uid);
    }

    return $icon;
}

function _get_oper_fio($oper_id)
{
    $fio = ibadmin_DB::get_oper_fio($oper_id);
    return $fio;
}

/**
 * @param $acc
 */
function _add_to_history($acc, $abs_id, $dt_sign)
{
    ibadmin_DB::prepare_access_history_table($acc);
    $data = _get_acc_access_list($acc, $abs_id);

    $json_data = base64_encode(json_encode($data));
    ibadmin_DB::add_to_access_history($acc, $json_data, $dt_sign);

}


function _get_acc_access_list($acc, $abs_id)
{
    $uids_data = ibadmin_DB::list_acc_access_users($acc, $abs_id);

    $users = array();

    if (!$uids_data) {
        return array();
    }

    foreach ($uids_data as $id => $data) {

        try {
            $fio = ibadmin_DB::get_user_fio($id);
        } catch (SystemException $e) {
            continue;
        }

        if ($fio) {
            $a = $data;
            $a['name'] = $fio;
            $a['icon'] = _get_icon($id);
            $users[] = $a;
        }
    }

    return $users;
}

function _get_ava_path_forfe($uid)
{
    return 'ib.php?do=getAva&uid=' . $uid . '&_ts=' . ab_rts(6) . '&_nts=%nts';
}

/**
 * подключение не
 * клиента банка
 */
function new_non_client()
{
    _create_operator_data_for_unclient();
    write_new_client(1);

}

/**
 * todo определить ей место
 * @param $string
 * @return string
 */
function translate($string)
{

    $replace = array(
        "А" => "A", "а" => "a",
        "Б" => "B", "б" => "b",
        "В" => "V", "в" => "v",
        "Г" => "G", "г" => "g",
        "Д" => "D", "д" => "d",
        "Е" => "E", "е" => "e",
        "Ё" => "E", "ё" => "e",
        "Ж" => "Zh", "ж" => "zh",
        "З" => "Z", "з" => "z",
        "И" => "I", "и" => "i",
        "Й" => "I", "й" => "i",
        "К" => "K", "к" => "k",
        "Л" => "L", "л" => "l",
        "М" => "M", "м" => "m",
        "Н" => "N", "н" => "n",
        "О" => "O", "о" => "o",
        "П" => "P", "п" => "p",
        "Р" => "R", "р" => "r",
        "С" => "S", "с" => "s",
        "Т" => "T", "т" => "t",
        "У" => "U", "у" => "u",
        "Ф" => "F", "ф" => "f",
        "Х" => "Kh", "х" => "kh",
        "Ц" => "Tc", "ц" => "tc",
        "Ч" => "Ch", "ч" => "ch",
        "Ш" => "Sh", "ш" => "sh",
        "Щ" => "Shch", "щ" => "shch",
        "Ы" => "Y", "ы" => "y",
        "Э" => "E", "э" => "e",
        "Ю" => "Yu", "ю" => "yu",
        "Я" => "Ya", "я" => "ya",
        "ъ" => "", "ь" => ""
    );

    return iconv("UTF-8", "UTF-8//IGNORE", strtr($string, $replace));
}

/**
 * собираем данные для оператора ДБО,
 * не являющегося клиентом банка
 */
function _create_operator_data_for_unclient()
{
    $_POST['f_cellphone'] = ab_secure(trim($_POST['cellphone']));
    unset($_POST['cellphone']);

    $_POST['f_ownerbd'] = ab_secure(trim($_POST['userbd']));
    unset($_POST['userbd']);

    $_POST['f_email'] = ab_secure(trim($_POST['email']));
    unset($_POST['email']);

    $_POST['f_lastname'] = ab_secure(trim($_POST['family']));
    unset($_POST['family']);
    $_POST['f_name'] = ab_secure(trim($_POST['name']));
    unset($_POST['name']);
    $_POST['f_fname'] = ab_secure(trim($_POST['father']));
    unset($_POST['father']);

    $_POST['f_Englastname'] = translate($_POST['f_lastname']);
    $_POST['f_Engname'] = translate($_POST['f_name']);
    $_POST['f_Engfname'] = translate($_POST['f_fname']);

    $_POST['f_full_name'] = $_POST['f_lastname'] . ' ' . $_POST['f_name'] . ' ' . $_POST['f_fname'];
    $_POST['f_full_Engname'] = $_POST['f_Englastname'] . ' ' . $_POST['f_Engname'] . ' ' . $_POST['f_Engfname'];

    $_POST['cfg'] = 'ibadmin_users';
    $_POST['f_abs_ids'] = '';
    $_POST['f_type_client'] = Local::MAN_ABS;

    $_POST['f_habitation_index'] = '';
    $_POST['f_habitation_city'] = '';
    $_POST['f_habitation_street'] = '';
    $_POST['f_habitation_home'] = '';
    $_POST['f_habitation_corp'] = '';
    $_POST['f_habitation_app'] = '';

    $_POST['f_registration_index'] = '';
    $_POST['f_registration_city'] = '';
    $_POST['f_registration_street'] = '';
    $_POST['f_registration_home'] = '';
    $_POST['f_registration_corp'] = '';
    $_POST['f_registration_app'] = '';

}

function _create_operator_data_for_fizclient($data)
{
    $_POST['f_cellphone'] = ab_secure(trim($data['f_cellphone']));
    $_POST['f_ownerbd'] = ab_secure(trim($data['f_ownerbd']));
    $_POST['f_email'] = ab_secure(trim($data['f_email']));


    $_POST['f_lastname'] = ab_secure(trim($data['f_lastname']));
    $_POST['f_name'] = ab_secure(trim($data['f_name']));
    $_POST['f_fname'] = ab_secure(trim($data['f_fname']));

    $_POST['f_Englastname'] = translate($data['f_lastname']);
    $_POST['f_Engname'] = translate($data['f_name']);
    $_POST['f_Engfname'] = translate($data['f_fname']);

    $_POST['f_full_name'] = $_POST['f_lastname'] . ' ' . $_POST['f_name'] . ' ' . $_POST['f_fname'];
    $_POST['f_full_Engname'] = $_POST['f_Englastname'] . ' ' . $_POST['f_Engname'] . ' ' . $_POST['f_Engfname'];

    $_POST['cfg'] = 'ibadmin_users';
    $_POST['f_abs_ids'] = $data['abs_id'];
    $_POST['f_type_client'] = Local::MAN2_ABS;

    $_POST['f_habitation_obl'] = $data['f_habitation_obl'];
    $_POST['f_habitation_index'] = $data['f_habitation_index'];
    $_POST['f_habitation_city'] = $data['f_habitation_city'];
    $_POST['f_habitation_street'] = $data['f_habitation_street'];
    $_POST['f_habitation_home'] = $data['f_habitation_home'];
    $_POST['f_habitation_corp'] = $data['f_habitation_corp'];
    $_POST['f_habitation_app'] = $data['f_habitation_app'];

    $_POST['f_registration_obl'] = $data['f_registration_obl'];
    $_POST['f_registration_index'] = $data['f_registration_index'];
    $_POST['f_registration_city'] = $data['f_registration_city'];
    $_POST['f_registration_street'] = $data['f_registration_street'];
    $_POST['f_registration_home'] = $data['f_registration_home'];
    $_POST['f_registration_corp'] = $data['f_registration_corp'];
    $_POST['f_registration_app'] = $data['f_registration_app'];

    $_POST['f_habitationEng_obl'] = translate($data['f_habitation_obl']);
    $_POST['f_habitationEng_index'] = translate($data['f_habitation_index']);
    $_POST['f_habitationEng_city'] = translate($data['f_habitation_city']);
    $_POST['f_habitationEng_street'] = translate($data['f_habitation_street']);
    $_POST['f_habitationEng_home'] = translate($data['f_habitation_home']);
    $_POST['f_habitationEng_corp'] = translate($data['f_habitation_corp']);
    $_POST['f_habitationEng_app'] = translate($data['f_habitation_app']);

    $_POST['f_registrationEng_obl'] = translate($data['f_registration_obl']);
    $_POST['f_registrationEng_index'] = translate($data['f_registration_index']);
    $_POST['f_registrationEng_city'] = translate($data['f_registration_city']);
    $_POST['f_registrationEng_street'] = translate($data['f_registration_street']);
    $_POST['f_registrationEng_home'] = translate($data['f_registration_home']);
    $_POST['f_registrationEng_corp'] = translate($data['f_registration_corp']);
    $_POST['f_registrationEng_app'] = translate($data['f_registration_app']);
}

/**
 * @param $client_data
 * @return mixed
 * @throws Exception
 */
function _get_phone($client_data)
{

    if (!isset($client_data['f_cellphone']) || !$client_data['f_cellphone']) {

        throw new UserException('Телефон пользователя не установлен.');
    }

    return $client_data['f_cellphone'];
}

/**
 * @param $uid
 * @return array
 * @throws UserException
 */
function _get_client_data($uid)
{
    $queId = ibadmin_DB::_search_user($uid);

    $row = $queId->fetch_assoc();

    if (!$row) {
        throw new UserException('Не удалось получить данные пользователя.');
    }

    return $row;
}

function _ibadmin_create_paypass($uid)
{
    $uid = (int)$uid;
    $mw = ibadmin_short_rnd(6);
    error_log(__METHOD__ . ' ' . $mw);
    $hach = hash('sha512', $mw);

    ibadmin_DB::_set_pay_password($uid, $hach);

    return $mw;

}

function _ibadmin_create_newlogin($uid, $row)
{
    $uid = (int)$uid;

    $login = UserTools::generate_login(base64_decode($row['f_Engname']), base64_decode($row['f_Englastname']));

    ibadmin_DB::_new_login($uid, $login);

    return $login;

}

function change_rights()
{
    global $ab;

    if (ab_rights('ibadmin_dbo_admin_') || ab_rights('ibadmin_dbo_edit_users_')) {

        try {

            $rights = ab_secure($_POST['rights']);
            $uid = (int)$_POST['uid'];
            ibadmin_DB::user_rights($uid, $rights);

            echo ab_result(1, array('message' => $ab['ibadmin.bank_p_changes']));
            write_log($uid, 'setNewRights', 1);


        } catch (UserException $e) {
            echo ab_result(0, array('message' => $e->getMessage()));
            write_log($uid, 'setNewRights', 0);

        } catch (SystemException $e) {
            error_log(__METHOD__ . $e->getMessage());
            write_log($uid, 'setNewRights', 0);
        }
    }

}


/**
 * Class SystemException
 * сообщения пишутся в логи
 */
class SystemException extends Exception
{

}

/**
 * сообщения передаются в диалоговое окно оператора
 * Class UserException
 */
class UserException extends Exception
{

}


