<?php

class ibadmin_DB
{
    static function _check_user($uid, $date)
    {
        global $Q;
        try {
            $queId = $Q->query("SELECT f_uid FROM t_ib_users WHERE f_ownerbd='$date' ");
            return $queId;
        } catch (Exception $e) {
            throw new Exception('_check_user ' . $e->getMessage());
        }
    }

    static function _search_user($uid)
    {
        global $Q;

        $uid = (int)$uid;
        $sql = "SELECT f_Englastname,f_Engname,f_cellphone,f_type_client FROM t_ib_users WHERE f_uid = '$uid'";

        $queId = $Q->query($sql);

        if (!$queId) {
            throw new SystemException("Query failed " . $sql);
        }

        return $queId;
    }

    static function _new_login($uid, $new_login)
    {
        global $Q;

        $sql = "UPDATE t_ib_users SET f_login='$new_login' WHERE f_uid='$uid'";
        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException("Query failed " . $sql);
        }

    }

    static function _block_unblock($block, $uid)
    {
        global $Q;
        try {
            if ($block == 0) $_f_sign_error = ",f_sign_error='0'";
            $Q->query("UPDATE t_ib_users SET f_block='$block' {$_f_sign_error} WHERE f_uid='$uid'");
        } catch (Exception $e) {
            throw new Exception('_block_unblock ' . $e->getMessage());
        }
    }

    static function _get_users_dbo()
    {
        global $Q;
        try {
            $queId = $Q->query("SELECT f_id, f_name FROM t_user WHERE f_rights LIKE '%operdbo%'");
            return $queId;
        } catch (Exception $e) {
            throw new Exception('_get_users_dbo ' . $e->getMessage());
        }
    }

    static function _set_pay_password($uid, $hach)
    {
        global $Q;

        $uid = (int)$uid;

        $sql = "UPDATE t_ib_users set f_pay_password='$hach' WHERE f_uid='$uid'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException("Query failed " . $sql);
        }

    }

    static function _get_tasks($date)
    {
        global $Q;
        try {
            $queId = $Q->query('SELECT *FROM t_ib_tasks WHERE STR_TO_DATE(f_date_perform, "%d-%m-%Y")=STR_TO_DATE("' . $date . '", "%d-%m-%Y") ORDER BY f_date_perform ASC');
            if ($queId) {
                while ($res = $queId->fetch_assoc()) {
                    $row[] = $res;
                }
            }
            return $row;
        } catch (Exception $e) {
            throw new Exception('_get_tasks ' . $e->getMessage());
        }
    }

    static function _search_client_be($fio)
    {
        global $Q;
        try {

            $sql = "SELECT * FROM t_ib_users WHERE UPPER(f_sea) LIKE '%$fio%'";
            $queId = $Q->query($sql);
            if ($queId) {
                while ($res = $queId->fetch_assoc()) {
                    $row[] = $res;
                }
            }
            return $row;
        } catch (Exception $e) {
            throw new Exception('_get_tasks ' . $e->getMessage());
        }
    }

    static function _check_client_be($fio, $cellphone, $bd)
    {
        global $Q;
        try {
            $fio = base64_encode($fio);
            $queId = $Q->query("SELECT f_uid FROM t_ib_users WHERE UPPER(f_full_name) = '$fio' AND f_cellphone='$cellphone' AND f_ownerbd = '$bd'");
            if ($queId) {
                while ($res = $queId->fetch_assoc()) {
                    $row[] = $res;
                }
            }
            return $row;
        } catch (Exception $e) {
            throw new Exception('_check_client_be ' . $e->getMessage());
        }
    }

    static function _get_oper_by_phone($cellphone)
    {
        global $Q;

        try {
            $phone = ab_secure($cellphone);
            $sql = "SELECT `f_uid`, `f_full_name`, `f_type_client` FROM t_ib_users WHERE f_cellphone='$phone'";
            $queId = $Q->query($sql);

            if(!$queId){
                throw new SystemException(__METHOD__.'query failed ' . $sql);
            }

            if($queId===TRUE){
                return false;
            }

            $res = $queId->fetch_assoc();

            if(!isset($res['f_full_name'])){
                throw new SystemException(__METHOD__." field f_full_name not find" . $sql);
            }

            return $res;

        } catch (Exception $e) {
            throw new Exception('_check_client_be ' . $e->getMessage());
        }
    }

    static function _get_accounts_join($uid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "SELECT * FROM t_ib_accounts WHERE f_user_uid = '$uid'";
        $queId = $Q->query($sql);

        $res = array();
        if (!$queId) {
            throw new Exception('get_accounts_join query failed ' . $sql);
        }

        while ($row = $queId->fetch_assoc()) {
            $res[] = $row;
        }

        return $res;
    }

    static function user_rights($uid, $rights)
    {
        global $Q;

        $uid = (int)$uid;
        $rights = addslashes($rights);

        $sql = "UPDATE `t_ib_users` SET `f_rights` = '{$rights}' WHERE `f_uid`=$uid";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . ' query failed ' . $sql);
        }

    }


    static function _check_client_phone($cellphone)
    {
        global $Q;

        $cellphone = ab_secure($cellphone);

        try {
            $queId = $Q->query("SELECT f_uid FROM t_ib_users WHERE f_cellphone='$cellphone'");
            if ($queId) {
                while ($res = $queId->fetch_assoc()) {
                    $row[] = $res;
                }
            }
            return $row;
        } catch (Exception $e) {
            throw new Exception('_check_client_be ' . $e->getMessage());
        }
    }

    /***
     * @param $lastname фамилия
     * @param $name имя
     * @param $fname отчество
     * @param $user_bd день рождения
     * @return array
     * @throws SystemException
     */
    static function _check_client_personal_data_new($lastname, $name, $fname, $user_bd)
    {
        global $Q;

        $params = array(
            'f_lastname' => $lastname,
            'f_name' => $name,
            'f_fname' => $fname,
            'f_ownerbd' => $user_bd
        );

        $fun = array(
            'f_lastname' => 'base64_encode',
            'f_name' => 'base64_encode',
            'f_fname' => 'base64_encode',
            'f_ownerbd' => 'ab_secure'
        );

        if (!array_values($params)) {
            throw new SystemException(__METHOD__ . ' params is empty.');
        }

        $t = array();
        foreach ($params as $key => $val) {
            $fun_c = $fun[$key];
            $t[] = $key . " ='" . $fun_c($val) . "'";
        }


        $where = implode(' AND ', $t);

        $sql = "SELECT `f_uid` FROM t_ib_users WHERE $where";

        error_log(__METHOD__.$sql);

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $uids = array();

        while ($row = $rc->fetch_assoc()) {

            if (!isset($row['f_uid'])) {
                throw new SystemException(__METHOD__ . " query failed $sql");
            }
            $uids[] = $row;

        }

        return $uids;

    }

    /**
     * @param $acc
     * @param $history_params
     * @throws SystemException
     */
    static function add_to_access_change_history($acc, $history_params)
    {
        global $Q, $ab;

        $acc = ab_secure($acc);

        $fun = array(
            'f_uid' => 'intval',
            'f_tim' => 'ab_secure',
            'f_view' => 'ab_secure',
            'f_pay' => 'ab_secure',
            'f_create' => 'ab_secure',
            'f_is_owner' => 'ab_secure',
            'f_limit1' => 'floatval',
            'f_limit30' => 'floatval',
            'f_limit90' => 'floatval',
            'f_limit360' => 'floatval',
            'f_operation' => 'ab_secure',
            'f_oper_uid' => 'intval',
            'f_source' => 'ab_secure',
            'f_proxy_date' => 'ab_secure',
            'f_operdoc' => 'base64_encode',
            'f_name' => 'base64_encode',
            'f_icon' => 'base64_encode',
            'f_oper_name' => 'base64_encode'
        );


        $table_name = preg_replace('/\%acc/', $acc, $ab['ibadmin.accaccesschange.history.table_name']);

        $t = array();

        foreach ($history_params as $key => $val) {
            $fun_curr = $fun[$key];
            $v = $fun_curr ? $fun_curr($val) : $val;
            $t[] = $key . ' = \'' . $v . '\'';
        }

        if ($t) {

            $sql = "INSERT INTO $table_name SET ";
            $sql .= implode(',', $t);
            $rc = $Q->query($sql);

            if (!$rc) {
                throw new SystemException(__METHOD__ . " query failed $sql");
            }
        }

    }

    /**
     * @param $acc
     * @throws SystemException
     */
    static function prepare_access_history_table($acc)
    {
        global $ab, $Q;

        $table_name = preg_replace('/\%acc/', $acc, $ab['ibadmin.accaccess.history.table_name']);

        $sql = preg_replace('/\%table/', $table_name, $ab['ibadmin.accaccess.history.table_create']);

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }

    /**
     * @param $acc
     * @throws SystemException
     */
    static function prepare_accesschange_history_table($acc)
    {
        global $ab, $Q;

        $table_name = preg_replace('/\%acc/', $acc, $ab['ibadmin.accaccesschange.history.table_name']);

        $sql = preg_replace('/\%table/', $table_name, $ab['ibadmin.accaccesschange.history.table_create']);

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }

    static function list_acc_access_users($acc, $absid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $acc = ab_secure($acc);

        $sql = "SELECT `f_user_uid`,`f_view`, `f_create` ,`f_pay`, `f_is_owner`, `f_proxy_date` FROM `t_ib_accounts` WHERE `f_account` LIKE BINARY '$acc'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . ' Query failed ' . $sql);
        }

        $uids_data = array();
        while ($row = $rc->fetch_assoc()) {
            if (isset($row['f_user_uid'])) {
                $date = '';
                if (trim($row['f_proxy_date'])) {
                    $date = DateTime::createFromFormat('Y-m-d', $row['f_proxy_date'])->format('Y-m-d');
                }

                $uids_data[$row['f_user_uid']] =
                    array('id' => $row['f_user_uid'],
                        'view' => $row['f_view'],
                        'create' => $row['f_create'],
                        'pay' => $row['f_pay'],
                        'is_owner' => $row['f_is_owner'],
                        'date' => $date
                    );
            }
        }

        //добавляем собственников

        $owners = array();
        $owners_uid = self::get_owner_uids_for_absid($absid);

        foreach ($owners_uid as $owner_uid) {

            $owners[$owner_uid] = array('id' => $owner_uid,
                'view' => 1,
                'create' => 1,
                'pay' => 1,
                'date' => '',
                'real_owner' => 1
            );
        }

        $uids_data = $uids_data + $owners;

        return $uids_data;

    }

    static function get_user_fio($id)
    {
        global $Q;

        $id = (int)$id;

        $sql = "SELECT `f_full_name`  FROM `t_ib_users` WHERE `f_uid`='{$id}'";

        $rc = $Q->query($sql);

        if (!$rc) {
            error_log('query failed ' . $sql);
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $arr = $rc->fetch_assoc();

        return base64_decode($arr['f_full_name']);
    }

    static function get_oper_fio($oper_id)
    {
        global $Q;

        $id = (int)$oper_id;

        $sql = "SELECT `f_name`  FROM `t_user` WHERE `f_id`='{$id}'";

        $rc = $Q->query($sql);

        if (!$rc) {
            error_log('query failed ' . $sql);
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $arr = $rc->fetch_assoc();

        return base64_decode($arr['f_name']);


    }

    static function check_icon($uid)
    {
        global $Q;

        $uid = (int)$uid;

        $sql = "SELECT `f_ownerIcon` FROM t_ib_users WHERE f_uid LIKE BINARY $uid";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $arr = $rc->fetch_assoc();
        $icon = '';

        if ($arr['f_ownerIcon']) {
            $icon = $arr['f_ownerIcon'];
        }

        return $icon;
    }

    static function get_owner_uids_for_absid($absid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $sql = "SELECT `uid`, `owner_uid`, `is_owner` FROM `t_uid_absid` WHERE `abs_id` = '{$absid}' AND prefs=0";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }

        $res = array();

        while ($row = $rc->fetch_assoc()) {
            if (trim($row['uid']) == trim($row['owner_uid'])) {
                $res[] = trim($row['owner_uid']);
            }
        }

        return $res;
    }

    static function add_to_access_history($acc, $json_data)
    {
        global $ab, $Q;

        $acc = ab_secure($acc);

        $table_name = preg_replace('/\%acc/', $acc, $ab['ibadmin.accaccess.history.table_name']);

        $sql = "INSERT INTO $table_name SET `f_content` = '{$json_data}'";

        $rc = $Q->query($sql);
        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }


    /**
     * при создании нового пользователя из клиента абс
     * @param $params
     * @return mixed
     * @throws SystemException
     */
    static function _check_client_personal_data($params)
    {
        global $Q;

        if (!$params) {
            throw new SystemException(__METHOD__ . ' params is empty.');
        }

        $t = array();
        foreach ($params as $key => $val) {
            $t[] = $key . " ='" . $val . "'";
        }
        $where = implode(' AND ', $t);

        $sql = "SELECT `f_uid` FROM t_ib_users WHERE $where";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $uids = array();

        while ($row = $rc->fetch_assoc()) {

            if (!isset($row['f_uid'])) {
                throw new SystemException(__METHOD__ . " query failed $sql");
            }
            $uids[] = $row;

        }

        return $uids;

    }

    static function _check_client_by_absid($abs_id)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $abs_id = ab_secure($abs_id);

        $sql = "SELECT `uid`, `owner_uid` FROM t_uid_absid WHERE `abs_id` ={$abs_id} AND `prefs` =0";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $t = array();

        while ($row = $rc->fetch_assoc()) {
            if (trim($row['uid']) === trim($row['owner_uid'])) {
                $t[] = $row['uid'];
            }
        }

        return count($t) ? 1 : 0;
    }

    static function _get_owners_absid_by_uid($uid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $uid = (int)$uid;

        $sql = "SELECT `abs_id` FROM t_uid_absid WHERE `uid` LIKE BINARY {$uid} AND `owner_uid` LIKE BINARY {$uid} AND `prefs` = 0";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

        $row = $rc->fetch_assoc();

        return isset($row['abs_id']) ? $row['abs_id'] : 0;
    }

    /**
     * предоставление прав к счету
     */
    static function set_access_account($params)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.ibadmin_accounts']);

        $d = array();
        foreach ($params as $key => $val) {
            $d[] = $key . '=\'' . ab_secure($val) . '\'';
        }
        $str = implode(',', $d);

        $sql = "INSERT INTO t_ib_accounts SET  $str ON DUPLICATE KEY UPDATE  $str ";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }

    }

    static function set_user_tokens($uid, $token_params)
    {
        global $Q, $ab;

        $query = preg_replace('/\$filter3/', $uid, $ab['ibadmin.table.ibadmin_user_tokens']);
        $Q->query($query);

        $fun = array(
            'f_tim' => 'ab_secure',
            'f_abs_name' => 'base64_encode',
            'f_abs_id' => 'ab_secure',
            'f_state' => 'ab_secure',
            'f_sea' => 'ab_secure',
        );

        $table_name = 't_user_tokens_' . $uid;

        $t = array();

        foreach ($token_params as $key => $val) {
            $fun_curr = $fun[$key];
            $v = $fun_curr ? $fun_curr($val) : $val;
            $t[] = $key . ' = \'' . $v . '\'';
        }

        if ($t) {
            $sql = "INSERT INTO $table_name SET ";
            $str = implode(',', $t);
            $sql .= $str;
            $sql .= ' ON DUPLICATE KEY UPDATE ' . $str;
            $rc = $Q->query($sql);

            if (!$rc) {
                throw new SystemException(__METHOD__ . " query failed $sql");
            }
        }


    }

    static function _get_users_fio($uid)
    {
        global $Q;
        $uid = (int)$uid;

        $sql = "SELECT `f_full_name` FROM t_ib_users WHERE f_uid=$uid";
        $queId = $Q->query($sql);

        if (!$queId) {
            throw new SystemException(__METHOD__ . " Query failed " . $sql);
        }

        $res = $queId->fetch_assoc();

        $fio = '';

        if (isset($res['f_full_name'])) {
            $fio = base64_decode($res['f_full_name']);
        }

        return $fio;
    }

    static function _set_owner_accounts($uid, $icusnum, $abs_ids)
    {
        global $Q;
        try {
            $owner_abs_ids = $abs_ids . ';' . $icusnum;
            /*$Q->query("UPDATE t_ib_users SET f_abs_ids='$owner_abs_ids' WHERE f_uid='$uid'");
            $Q->query("DELETE from t_ib_accounts WHERE f_user_uid='$uid' AND f_abs_id='$abs_ids'");*/
        } catch (Exception $e) {
            throw new Exception('_check_client_be ' . $e->getMessage());
        }
    }

    static function _get_accounts_by_uid_absid($uid, $abs_id)
    {
        global $Q, $ab;
        try {
            $abs_id = ab_secure($abs_id);
            $uid = (int)$uid;
            $Q->query($ab['ibadmin.table.ibadmin_accounts']);
            $sql = "SELECT `f_account` from t_ib_accounts WHERE f_user_uid='$uid' AND f_abs_id='$abs_id'";
            $rc = $Q->query($sql);

            $res = array();
            if ($rc) {
                while ($row = $rc->fetch_assoc()) {
                    $res[$row['f_account']] = $row['f_account'];
                }
            }

            return $res;

        } catch (mysqli_sql_exception $e) {
            throw new SystemException(__METHOD__ . $e->getMessage());
        }
    }

    static function _delete_accounts_by_uid_absid($uid, $abs_id)
    {
        global $Q, $ab;
        try {
            $abs_id = ab_secure($abs_id);
            $Q->query($ab['ibadmin.table.ibadmin_accounts']);
            $sql = "DELETE from t_ib_accounts WHERE f_user_uid='$uid' AND f_abs_id='$abs_id'";

            $Q->query($sql);
        } catch (mysqli_sql_exception $e) {
            throw new SystemException(__METHOD__ . $e->getMessage());
        }
    }

    static function delete_from_access($uid, $acc)
    {
        global $Q, $ab;

        $uid = (int)$uid;
        $acc = ab_secure($acc);
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "DELETE FROM `t_ib_accounts` WHERE `f_user_uid`=$uid AND `f_account`='{$acc}'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }

    static function update_acc_access($uid, $acc, $params)
    {
        global $Q, $ab;
        $t = array();

        foreach ($params as $key => $val) {
            $t[] = $key . '=\'' . ab_secure($val) . '\'';
        }

        $str = implode(',', $t);
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "UPDATE t_ib_accounts SET " . $str . "  WHERE `f_user_uid` = $uid  AND `f_account`='{$acc}'";

        $Q->query($sql);

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }

    static function get_acc_access_data($uid, $acc)
    {
        global $Q, $ab;

        $uid = (int)$uid;
        $acc = ab_secure($acc);
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "SELECT * FROM t_ib_accounts WHERE f_user_uid=$uid AND f_account = '{$acc}'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }

        $arr = $rc->fetch_assoc();

        return $arr;

    }


    static function get_access_user_data($uid, $acc)
    {
        global $Q, $ab;

        $acc = ab_secure($acc);
        $uid = (int)$uid;
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "SELECT `f_view`,`f_create` ,`f_pay`,`f_is_owner`, `f_proxy_date` FROM `t_ib_accounts` WHERE `f_account` LIKE BINARY '$acc' AND `f_user_uid` LIKE BINARY $uid";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . ' Query failed ' . $sql);
        }

        $row = $rc->fetch_assoc();

        return $row;
    }

    //todo
    static function get_acc_access_count($uid, $abs_id)
    {
        global $Q, $ab;

        $uid = (int)$uid;
        $abs_id = ab_secure($abs_id);
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "SELECT count(*) cnt FROM t_ib_accounts WHERE f_user_uid=$uid  AND  f_abs_id = '{$abs_id}'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }

        $arr = $rc->fetch_assoc();

        return $arr['cnt'];
    }

    static function delete_from_uid_absid($uid, $abs_id)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $uid = (int)$uid;
        $abs_id = ab_secure($abs_id);

        $sql = "DELETE FROM t_uid_absid WHERE uid=$uid AND abs_id = '{$abs_id}' AND `prefs`=1";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }
    }

    static function _set_uid_absid($params)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $sql = "INSERT INTO `t_uid_absid` SET ";

        $t = array();
        foreach ($params as $key => $params) {
            $t[] = $key . '=\'' . ab_secure($params) . '\'';
        }


        $str = implode(',', $t);
        $sql .= $str;

        $sql .= ' ON DUPLICATE KEY UPDATE ' . $str;

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException("Query failed " . $sql);
        }
    }


    static function _update_operator($uid, $params)
    {
        global $Q;

        $uid = (int)$uid;

        $t = array();
        foreach ($params as $key => $val) {
            $t[] = $key . '=\'' . ab_secure($val) . '\'';
        }

        $fields = implode(',', $t);

        $sql = "UPDATE `t_ib_users` SET %fields  WHERE `f_uid` LIKE BINARY $uid";

        $sql = preg_replace('/\%fields/', $fields, $sql);

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed $sql");
        }

    }

    /**
     * @param $abs_id
     * @param $owner_uid
     * @throws SystemException
     */
    static function _update_uid_absid($abs_id, $owner_uid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $owner_uid = (int)$owner_uid;
        $abs_id = ab_secure($abs_id);

        $sql = "UPDATE `t_uid_absid` SET `owner_uid` = $owner_uid  WHERE `abs_id` LIKE BINARY '{$abs_id}' AND `is_owner` LIKE BINARY '0' AND `owner_uid` LIKE BINARY '0'";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException("Query failed " . $sql);
        }
    }

    static function _update_accounts_owner_uid($abs_id, $owner_uid)
    {
        global $Q, $ab;

        $owner_uid = (int)$owner_uid;
        $abs_id = ab_secure($abs_id);
        $Q->query($ab['ibadmin.table.ibadmin_accounts']);
        $sql = "UPDATE `t_ib_accounts` SET `f_owner_uid` = '$owner_uid'  WHERE `f_abs_id` LIKE BINARY '{$abs_id}' AND `f_owner_uid` =0";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException("Query failed " . $sql);
        }
    }

    static function _get_uid_absids($uid)
    {
        global $Q, $ab;

        /**
         * uid - идентификатор дбо оператора
         * is_owner - может означать назначенного владельца для abs_id
         * abs_id - идентификатор клиента абс
         * ctype - тип клиента (юрик, физик)
         * owner_uid - истиный владелец идентификатора клиента абс, может быть не заполнен, если клиента нет в таблице t_ib_users
         * считаем, что оператор является истинным владельцем идентификатора абс только в случае, если uid=owner_uid
         * prefs - настройки доступа к счету 0 - получать все счета по данному клиенту абс, 1 - только те, которые находятся в таблице t_ib_accounts
         */
        $uid = (int)$uid;
        $Q->query($ab['ibadmin.table.uid_absid']);

        $sql = "SELECT `uid`,`is_owner`,`abs_id`,`ctype`,`owner_uid`,`prefs` FROM `t_uid_absid` WHERE `uid` LIKE BINARY $uid";

        $rc = $Q->query($sql);
        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed " . $sql);
        }

        $res = array();

        while ($row = $rc->fetch_assoc()) {
            $res[$row['abs_id']] = array(
                'uid' => $row['uid'],
                'is_owner' => $row['is_owner'],
                'abs_id' => $row['abs_id'],
                'ctype' => $row['ctype'],
                'owner_uid' => $row['owner_uid'],
                'prefs' => $row['prefs']);
        }

        return $res;
    }


    static function get_absid_by_acc($uid, $acc)
    {
        global $Q, $ab;

        $acc = ab_secure($acc);
        $uid = (int)$uid;

        $Q->query($ab['ibadmin.table.ibadmin_accounts']);

        $sql = "SELECT `f_abs_id` FROM `t_ib_accounts` WHERE `f_account` = '{$acc}' AND `f_user_uid` LIKE BINARY {$uid}";

        $rc = $Q->query($sql);

        if (!$rc) {
            throw new SystemException(__METHOD__ . " Query failed $sql");
        }

        $arr = $rc->fetch_assoc();

        return isset($arr['f_abs_id']) ? trim($arr['f_abs_id']) : '';
    }

    /**
     * @param $absid
     */
    static function _get_owner_by_absid($absid)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $absid = ab_secure($absid);

        $sql = "SELECT `uid`,`is_owner`,`owner_uid` FROM `t_uid_absid` WHERE `abs_id` LIKE BINARY '$absid' AND `prefs`= 0";

        $rc = $Q->query($sql);
        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed " . $sql);
        }

        $res = array();

        while ($row = $rc->fetch_assoc()) {
            if (trim($row['uid']) === trim($row['owner_uid'])) {
                $res[] = trim($row['owner_uid']);
            }
        }

        return $res;
    }

    static function truncate_hash($uid)
    {
        global $ab, $Q;

        $uid = (int)($uid);
        //удаляем временные таблицы пользователя
        try {
            $tn_temp = str_replace('%uid', $uid, $ab['ibadmin.back.accounts.temp.table_name']);
            $sql = "TRUNCATE $tn_temp";
            $Q->query($sql);

            $tn_temp = preg_replace(array('/%uid/'), array($uid), $ab['ibadmin.back.accstm.temp.table_name']);
            $sql = "TRUNCATE $tn_temp";
            $Q->query($sql);
        } catch (mysqli_sql_exception $e) {
            error_log(__METHOD__ . $e->getMessage());
        }
    }

    static function delete_acc_from_hash($uid, $acc)
    {
        global $ab, $Q;

        $uid = (int)($uid);
        $acc = addslashes($acc);
        try {
            $tn_temp = str_replace('%uid', $uid, $ab['ibadmin.back.accounts.temp.table_name']);
            $sql = "DELETE FROM $tn_temp WHERE `f_account` = '$acc'";
            $Q->query($sql);

            $tn_temp = preg_replace(array('/%uid/'), array($uid), $ab['ibadmin.back.accstm.temp.table_name']);
            $sql = "DELETE FROM $tn_temp WHERE `account` = '$acc'";
            $Q->query($sql);

        } catch (mysqli_sql_exception $e) {

        }
    }

    static function get_owner_uid_by_absid($abs_id)
    {
        global $Q, $ab;

        $Q->query($ab['ibadmin.table.uid_absid']);

        $abs_id = ab_secure($abs_id);

        $sql = "SELECT `owner_uid`, `uid`  FROM `t_uid_absid` WHERE `abs_id` LIKE BINARY '{$abs_id}' AND `is_owner` LIKE BINARY '1' ORDER BY tim LIMIT 1";

        $rc = $Q->query($sql);
        if (!$rc) {
            throw new SystemException(__METHOD__ . " query failed " . $sql);
        }

        $res = array();

        while ($row = $rc->fetch_assoc()) {
            if ($row['owner_uid'] == $row['uid']) {
                $res[] = $row['owner_uid'];
            }
        }

        $res = array_unique($res);
        if (count($res) > 1) {
            throw new SystemException(__METHOD__ . " owner_uid has more than one  absid");
        }

        return isset($res[0]) ? $res[0] : 0;
    }
}