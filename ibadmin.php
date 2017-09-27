<?php
/**
 * Created by PhpStorm.
 * User: k.soboleva
 * Date: 15.04.2016
 * Time: 10:29
 */

class Local{

    const FIR_ABS = 2; // юрик
    const MAN_ABS = 1; // физик
    const MAN2_ABS = 9; // физик Из дополнительной  базы rbs
    const MIP_ABS = 3; // ип
    
    const ACCTYPE_NOTDEF = 'Не определен';

    static public function get($key)
    {
        $d = self::$$key;

        if (!$d) {
            throw new SystemException(__METHOD__ . "parametr $key not find or is empty");
        }

        return $d;

    }
}

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING ^ E_DEPRECATED);
mb_internal_encoding('UTF-8');
setlocale(LC_ALL, 'ru_RU.UTF-8');

require_once 'ab/abmain.php';
require_once 'ab/site/_conf_sms.php';

require_once 'ibadmin_c.php';

spl_autoload_register('autoload');

function autoload($class_name)
{
    $filename = strtolower($class_name) . '.php';
    $file = 'api/classes/' . $filename;

    if (file_exists($file) == false)
        return false;
    include($file);
}

function recurcive_ab_secure(&$val, $key)
{
    $dangerKeys = array(
      'conf_word_new',
      'conf_word_new2',
      'conf_login_my',
      'conf_login_my2',
      'conf_login_suffix',
      'login'
    );

    if (in_array($key, $dangerKeys)) {
	$val = '*****';
    } else {
        $val = ab_secure($val);
    }
}

function write_log($uid, $operation, $status, $array = [])
{
    global $ab_user, $ab, $Q;
    $array = array_merge($array, array('post' => $_POST, 'get' => $_GET, 'cookie' => $_COOKIE, 'server' => $_SERVER));
    array_walk_recursive($array, 'recurcive_ab_secure');

    if ($uid > 0) {
        $sql = "INSERT INTO t_ib_editors_log SET `f_tim` = '" . date('Y-m-d H:i:s') . "', `f_editor` = '" . $ab_user['name'] . "', `f_user_uid` = '" . $uid . "', `f_operation` = '" . $operation . "', `f_status` = '" . $status . "', `f_editor_id` = '" . $ab_user['id'] . "', `f_note` = '" . base64_encode(json_encode($array)) . "'";
        error_log($sql);
        $Q->query($sql);
    }
}

function get_uid()
{
    global $Q, $ab_user, $ab;

    if (ab_rights('ibadmin_dbo_edit_users_') || ab_rights('ibadmin_dbo_admin_')
        || ab_rights('ibadmin_account_') || ab_rights('ibadmin_upload_')
    ) {
        $fio = (isset($_POST['fio'])) ? base64_encode(ab_secure($_POST['fio'])) : '';
        $cellphone = (isset($_POST['cellphone'])) ? ab_secure($_POST['cellphone']) : '';
        $sql = "SELECT f_uid from t_ib_users WHERE f_full_name = '$fio' AND f_cellphone = '$cellphone'";

        $queId = $Q->query($sql);
        if ($queId) {
            while ($res = $queId->fetch_assoc()) {
                $result[] = $res;
            }
        }
        echo ab_result($queId ? 1 : 0, array('data' => $result));
    } else
        echo ab_result(0, array('message' => $ab['ibadmin.bank_bank_authError']));
}

function ibadmin_act_deact()
{
    global $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $acc = (isset($_REQUEST['acc'])) ? ab_secure($_REQUEST['acc']) : '';
        $settype = (isset($_REQUEST['settype'])) ? ab_secure($_REQUEST['settype']) : '';
        if ($acc != '' or $settype != '') {
            $settype = ($settype == 1) ? 0 : 1;
            ibadmin_DB::_act_deact($acc, $settype);
            echo ab_result(1);
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_noData']));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_bank_authError']));
}

function ibadmin_check_user()
{
    global $Q, $ab_user, $ab;
    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_REQUEST['uid'])) ? ab_secure($_REQUEST['uid']) : '';
        $date = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : '';

        $_date[] = substr($date, 4);
        $_date[] = substr($date, 2, 2);
        $_date[] = substr($date, 0, 2);

        $checkdate = implode("-", $_date);
        $queId = ibadmin_DB::_check_user($uid, $checkdate);

        $row = $queId->fetch_assoc();

        if (is_array($row) and $row['f_uid']) {
            $r = 1;
            echo ab_result($r, array('data' => $row));
        } else {
            $r = 0;
            echo ab_result($r, array('message' => $ab['var.ibadmin.bank_errorCheckClient']));
        }

        write_log($uid, "checkUser", $r);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function ibadmin_users_logs()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_logs_')) {
        $cntrl = 20;
        $page = (isset($_REQUEST['page'])) ? ab_secure($_REQUEST['page']) : 0;
        $filter = (isset($_REQUEST['filter'])) ? ab_secure($_REQUEST['filter']) : '';

        switch ($_REQUEST['oper']) {
            case 'day':
                $search = ' WHERE  STR_TO_DATE(f_tim, "%d.%m.%Y")=STR_TO_DATE("' . date('d.m.Y') . '", "%d.%m.%Y")';
                break;
            case 'week':
                $search = ' WHERE  STR_TO_DATE(f_tim, "%d.%m.%Y")<=STR_TO_DATE("' . date('d.m.Y') . '", "%d.%m.%Y") AND STR_TO_DATE(f_tim, "%d.%m.%Y")>=STR_TO_DATE("' . date('d.m.Y', strtotime("-7 DAY")) . '", "%d.%m.%Y")';
                break;
            case 'qr':
                $search = ' WHERE  STR_TO_DATE(f_tim, "%d.%m.%Y")<=STR_TO_DATE("' . date('d.m.Y') . '", "%d.%m.%Y") AND STR_TO_DATE(f_tim, "%d.%m.%Y")>=STR_TO_DATE("' . date('d.m.Y', strtotime("-3 MONTH")) . '", "%d.%m.%Y")';
                break;
            case 'year':
                $search = ' WHERE  STR_TO_DATE(f_tim, "%d.%m.%Y")<=STR_TO_DATE("' . date("d.m.Y") . '", "%d.%m.%Y") AND STR_TO_DATE(f_tim, "%d.%m.%Y")>=STR_TO_DATE("' . date('d.m.Y', strtotime("-365 DAY")) . '", "%d.%m.%Y")';
                break;
        }

        $queId = $Q->query('SHOW TABLES');

        if ($queId) {
            $key = 0;
            while ($row = $queId->fetch_assoc()) {
                if (stristr($row['Tables_in_admin_db'], 't_ib_user_') == true) {
                    if (isset($sql)) {
                        $sql .= " UNION ";
                    }
                    $sql .= "SELECT *FROM " . $row['Tables_in_admin_db'] . $search;
                    if ($filter != '') {
                        $sql .= " AND f_ip LIKE '%$filter%' OR f_action_type LIKE '%$filter%'";
                    }
                }
            }

            $tables = $Q->query($sql . " ORDER BY STR_TO_DATE(f_tim, '%d.%m.%Y') ASC");

            if ($tables) {
                while ($res = $tables->fetch_assoc()) {
                    $result[$key] = $res;
                    $date[$res['f_action_type']][date('d-m-Y', strtotime($res['f_tim']))] += 1;

                    if (in_array(date('d-m-Y', strtotime($res['f_tim'])), $opt) == false)
                        $opt[] = date('d-m-Y', strtotime($res['f_tim']));

                    $key++;
                }

                $count_all = count($result);
                $count = null;
                if (count($result) > $cntrl) {
                    $count = ceil(count($result) / $cntrl);
                }
                $result = array_slice($result, $page * $cntrl, $cntrl);
            }
        }

        $r = ($queId) ? 1 : 0;
        echo ab_result($r, array('data' => $result, 'count' => $count, 'active' => $page, 'statistics' => $date, 'date' => $opt, 'count_all' => $count_all, 'sql' => $sql));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_search_users_logs()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $cntrl = 20;
        $d = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : date('d.m.Y');
        $nameoper = (isset($_REQUEST['nameoper'])) ? ab_secure($_REQUEST['nameoper']) : 0;
        $search = " WHERE  STR_TO_DATE(f_tim, '%d.%m.%Y')=STR_TO_DATE('$d', '%d.%m.%Y') AND f_action_type='$nameoper'";
        $queId = $Q->query('SHOW TABLES');

        if ($queId) {
            $key = 0;
            while ($row = $queId->fetch_assoc()) {
                if (stristr($row['Tables_in_admin_db'], 't_ib_user_') == true) {
                    $tables = $Q->query('SELECT STR_TO_DATE(f_tim, "%d.%m.%Y") AS f_date,f_tim,f_ip,f_browser,f_action_type,f_info FROM ' . $row['Tables_in_admin_db'] . $search);
                    if ($tables) {
                        while ($res = $tables->fetch_assoc()) {
                            $result[$key] = $res;
                            $date[$res['f_action_type']][date('d-m-Y', strtotime($res['f_date']))] += 1;

                            if (in_array(date('d-m-Y', strtotime($res['f_date'])), $opt) == false)
                                $opt[] = date('d-m-Y', strtotime($res['f_date']));
                            $key++;
                        }
                    }
                }
            }
        }

        echo ab_result($queId ? 1 : 0, array('data' => $result, 'statistics' => $date, 'date' => $opt,));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function ibadmin_user_block()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_REQUEST['uid'])) ? ab_secure($_REQUEST['uid']) : '';
        $oper = (isset($_REQUEST['oper'])) ? ab_secure($_REQUEST['oper']) : '';

        $oper = ($oper === '0' || $oper === 0) ? 0 : 1;
        ibadmin_DB::_block_unblock($oper, $uid);

        echo ab_result(1);

        switch ($oper) {
            case 0:
                $operation = "userUnBlock";
                break;
            default:
                ibadmin_DB::truncate_hash();
                $operation = "userBlock";
                break;
        }


        write_log($uid, $operation, 1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function ibadmin_get_tasks()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $date = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : date('d-m-Y');
        echo ab_result($queId ? 1 : 0, array('data' => $row));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_get_tasks_date()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $date = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : date('m-Y');
        $res = ibadmin_DB::_get_tasks($date);
        echo ab_result($res ? 1 : 0, array('data' => $res));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_get_users_dbo()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $queId = ibadmin_DB::_get_users_dbo();
        if ($queId) {
            $key = 0;
            while ($res = $queId->fetch_assoc()) {
                $res['f_name'] = base64_decode($res['f_name']);
                $row[$key] = $res;
                $key++;
            }
        }

        echo ab_result($queId ? 1 : 0, array('data' => $row));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_set_task()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $header = (isset($_REQUEST['header'])) ? ab_secure($_REQUEST['header']) : '';
        $description = (isset($_REQUEST['description'])) ? ab_secure($_REQUEST['description']) : '';
        $perform = (isset($_REQUEST['perform'])) ? ab_secure($_REQUEST['perform']) : '';
        $status = (isset($_REQUEST['status'])) ? ab_secure($_REQUEST['status']) : '';
        $priority = (isset($_REQUEST['priority'])) ? ab_secure($_REQUEST['priority']) : '';
        $execution = (isset($_REQUEST['execution'])) ? ab_secure($_REQUEST['execution']) : '';
        $creator = (isset($_REQUEST['creator'])) ? ab_secure($_REQUEST['creator']) : '';
        $creator_id = (isset($_REQUEST['creator_id'])) ? ab_secure($_REQUEST['creator_id']) : '';
        $tim = date('d-m-Y H:i:s');

        $sql = "INSERT INTO t_ib_tasks (f_tim, f_mtim, f_header, f_description, f_date_perform, f_status, f_priority, f_execution, f_creator, f_id_creator) VALUES ('$tim', '$tim', '$header', '$description', '$perform', '$status', '$priority', '$execution', '$creator', '$creator_id')";
        $Q->query($sql);
        echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_task_analitic_status()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $date = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : '';
        $queId = $Q->query("SELECT count(*) AS f_count,f_status,f_execution,t_user.f_name,STR_TO_DATE(f_date_perform, '%d-%m-%Y') AS f_tim FROM t_ib_tasks LEFT JOIN t_user ON  t_ib_tasks.f_execution=t_user.f_id WHERE f_date_perform LIKE '%$date%' GROUP BY f_execution,f_status,STR_TO_DATE(f_date_perform, '%d-%m-%Y')");
        if ($queId) {
            $key = 0;
            while ($res = $queId->fetch_assoc()) {
                $res['f_name'] = base64_decode($res['f_name']);
                $row[$key] = $res;
                $key++;
            }
        }

        echo ab_result($queId ? 1 : 0, array('data' => $row));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_task_auto_update()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $date = (isset($_REQUEST['date'])) ? ab_secure($_REQUEST['date']) : '';
        $Q->query("UPDATE t_ib_tasks set f_status=4 WHERE f_status!=3 AND STR_TO_DATE(f_date_perform,'%d-%m-%Y')<STR_TO_DATE('$date', '%d-%m-%Y')");
        echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_editors_statistics()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $queId = $Q->query("SELECT count(*), f_editor FROM t_ib_editors_log GROUP BY f_editor");
        if ($queId) {
            $key = 0;
            while ($res = $queId->fetch_assoc()) {
                $row[$key] = $res;
                $key++;
            }
        }

        echo ab_result($queId ? 1 : 0, array('data' => $row));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_is_dir()
{
    global $ab_user, $SERV_root, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_upload_')) {
        $uid = (isset($_REQUEST['uid'])) ? ab_secure($_REQUEST['uid']) : '';
        $dir = $SERV_root . '/data/ibadmin_upload_user_doc/';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (!is_dir($dir . $uid . '/')) $r = 0; else $r = 1;
        echo ab_result($r);
        // write_log($uid, "fileUpload", $r);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_view()
{
    global $Q, $ab, $ab_user, $ab;
    
    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $r = 0;
        $res = array();
        $err = '';
        $cfg = ab_secure($_GET['cfg']);
        $datebegin = ab_secure($_GET['datebegin']);
        $dateend = ab_secure($_GET['dateend']);
        $uid = ab_secure($_POST['uid']);

        /*parse cfg*/
        $cfg = ab_get_table_local('view', $cfg);

        if ($cfg) {
            $rights = ab_cfg_item('rights', $cfg);

            if (ab_rights($rights)) {
                /*prepare*/

                switch (ab_cfg_item('table', $cfg) == 'ibadmin_user_log') {
                    case 'ibadmin_user_log':
                        $table = "t_ib_user_" . $uid . "_logs";
                        break;

                    default:
                        $table = ab_cfg_item('table', $cfg);
                        break;
                }

                $query = ab_add_query_vars(ab_cfg_item('query', $cfg), $cfg);
                $query = str_replace('$table', $table, $query);

                $hd = ab_cfg_item('heads', $cfg);
                $fields = ab_cfg_item('fields', $cfg);
                $types = ab_cfg_item('types', $cfg);
                $classes = ab_cfg_item('classes', $cfg);
                $more = ab_cfg_item('more', $cfg);

                $pp = ab_cfg_item('pp', $cfg);
                $dpp = intval(ab_cfg_item('dpp', $cfg));
                $page = intval(ab_secure($_REQUEST['page']));
                $gpp = ab_secure($_REQUEST['pp']);
                if ($gpp) {
                    $dpp = $gpp;
                }

                $edit = ab_cfg_item('edit', $cfg);
                $editCfg = ab_cfg_item('editCfg', $cfg);
                $editClick = ab_cfg_item('editClick', $cfg);

                $seaSet = ab_cfg_item('search', $cfg);
                $search = trim(ab_secure($_GET['search']));

                $orderSet = ab_cfg_item('order', $cfg);
                $order = trim(ab_secure($_GET['order']));

                $filter1Set = ab_cfg_item('filter1', $cfg);
                $filter1 = trim(ab_secure($_GET['filter1']));

                $filter2Set = ab_cfg_item('filter2', $cfg);
                $filter2 = trim(ab_secure($_GET['filter2']));

                $filter3Set = ab_cfg_item('filter3', $cfg);
                $filter3 = trim(ab_secure($_GET['filter3']));

                /*filters*/
                $filter = '';
                $sort = '';

                /*search*/
                if ($seaSet && $search) {
                    $filter = '(' . str_replace('$val', mb_strtoupper($search), $seaSet) . ')';
                }

                /*filter1*/
                if ($datebegin && ab_cfg_item('table', $cfg) == 'ibadmin_user_log') {
                    $filter .= ($filter ? ' AND ' : '') . '( str_to_date(f_tim,"%d.%m.%Y")>=str_to_date("' . str_replace('-', '.', $datebegin) . '","%d.%m.%Y"))';
                } elseif ($datebegin) {
                    $filter .= ($filter ? ' AND ' : '') . 'f_tim >="' . $datebegin . '"';
                }

                /*filter1*/

                if ($dateend && ab_cfg_item('table', $cfg) == 'ibadmin_user_log') {
                    $filter .= ($filter ? ' AND ' : '') . '( str_to_date(f_tim,"%d.%m.%Y")<= str_to_date("' . str_replace('-', '.', $dateend) . '","%d.%m.%Y"))';
                } elseif ($dateend) {
                    $filter .= ($filter ? ' AND ' : '') . 'f_tim >="' . $dateend . '"';
                }

                /*filter1*/

                if ($filter1Set) {
                    $fil = explode(',', $filter1Set);
                    $filter1Set = $fil[0] . ',' . $fil[1] . ',' . $fil[2];

                    $filter1 = $filter1 ? $filter1 : $fil[1];
                    if ($filter1 === 0 || $filter1 === '0' || $filter1) {
                        if ($fil[3]) {
                            $filter .= ($filter ? ' AND ' : '') . '(' . str_replace('$val', $filter1, $fil[3]) . ')';
                        } else {
                            $filter1 = '';
                        }
                    }
                }

                /*filter2*/
                if ($filter2Set) {
                    $fil = explode(',', $filter2Set);
                    $filter2Set = $fil[0] . ',' . $fil[1] . ',' . $fil[2];

                    $filter2 = $filter2 ? $filter2 : $fil[1];

                    if ($filter2 === 0 || $filter2 === '0' || $filter2) {
                        if ($fil[3]) {
                            $filter .= ($filter ? ' AND ' : '') . '(' . str_replace('$val', $filter2, $fil[3]) . ')';
                        } else {
                            $filter2 = '';
                        }
                    }
                }

                /*filter3*/
                if ($filter3Set) {
                    $fil = explode(',', $filter3Set);
                    $filter3Set = $fil[0] . ',' . $fil[1] . ',' . $fil[2];

                    $filter3 = $filter3 ? $filter3 : $fil[1];
                    if ($filter3 === 0 || $filter3 === '0' || $filter3) {
                        if ($fil[3]) {
                            $filter .= ($filter ? ' AND ' : '') . '(' . str_replace('$val', $filter3, $fil[3]) . ')';
                        } else {
                            $filter3 = '';
                        }
                    }
                }

                /*order*/
                if ($orderSet) {
                    $ors = explode(',', $orderSet);
                    $order = $order ? $order : $ors[1];
                    if ($order === 0 || $order === '0' || $order) {
                        $srt = ab_name_by_value($order, ab_ps($ors[0]));
                        if ($srt) {
                            $sort = ' ORDER BY ' . $srt;
                        } else {
                            $order = '';
                        }
                    }
                }

                $filter = $filter ? (strstr(strtoupper($query), 'WHERE') != '' ? ' AND ' : ' WHERE ') . $filter : '';
                $filter = str_replace(array('$search', '$order', '$filter1', '$filter2', '$filter3'), array($search, $order, $filter1, $filter2, $filter3), $filter);

                /*query*/
                $data = array();
                $lim = '';
                $off = '';

                /*lim*/
                if ($dpp) $lim = ' LIMIT ' . $dpp;

                /*total*/
                $que = "SELECT count(*) FROM $table WHERE f_uid ='$uid'";
                if (strstr(strtoupper($query), ' FROM ') != '') {
                    $qa = explode(' FROM ', $query);
                    $qa = explode(' ORDER BY ', $qa[1]);
                    $que = 'SELECT count(*) FROM ' . $qa[0] . ' ' . $filter;
                }

                $queId = $Q->query($que);
                if ($queId) {
                    $total = $queId->fetch_row();
                    $total = intval($total[0]);
                } else {
                    $total = 0;
                }

                /*page*/
                if ($page > 1 && $dpp) {
                    $off = ($page - 1) * $dpp;
                    if ($off >= $total) {
                        $page = floor($total / $dpp) + 1;
                        if ($page < 2 || $off == $total) {
                            $off = 0;
                            $page = 1;
                        } else {
                            $off = ($page - 1) * $dpp;
                        }
                    }

                    $off = ' OFFSET ' . $off;
                } else {
                    $page = 1;
                }

                if (strstr(strtoupper($query), ' FROM ') == '') {
                    $query = $query . ' FROM ' . $table . ' WHERE f_uid="' . $uid . '"';
                }

                $orb = strpos(strtoupper($query), ' ORDER BY ');
                if ($orb != false) {
                    if ($filter != '') {
                        $query = substr($query, 0, $orb) . ' ' . $filter . substr($query, $orb);
                        $filter = '';
                    }
                    $sort = '';
                }

                $que = $query . $filter . $sort . $lim . $off;
                $que = str_replace(array('$search', '$order', '$filter1', '$filter2', '$filter3'), array($search, $order, $filter1, $filter2, $filter3), $que);

                $queId = $Q->query($que);

                /*check types*/
                $t = explode(',', $types);
                $f = explode(',', $fields);
                $cf = count($f);
                $h = explode(',', $hd);
                $c = explode(',', $classes);

                $fields = '';
                $types = '';
                $heads = '';
                $classes = '';

                for ($i = 0; $i < count($f); $i++) {

                    if ($f[$i] == 'delete' and !ab_rights($edit))
                        $t[$i] = 'skip';

                    if ($t[$i] != 'skip') {
                        if ($i > 0) {
                            $types .= ',';
                            $heads .= ',';
                            $fields .= ',';
                            $classes .= ',';
                        }
                        $t[$i] = trim($t[$i]);
                        $h[$i] = trim($h[$i]);
                        $f[$i] = trim($f[$i]);
                        $c[$i] = trim($c[$i]);

                        $types .= $t[$i];
                        $heads .= $h[$i];
                        $fields .= $f[$i];
                        $classes .= $c[$i];
                    }
                }

                $r = $queId ? 1 : 0;
                if ($r) {
                    while ($ar = $queId->fetch_row()) {
                        $dat = array();
                        for ($i = 0; $i < $cf; $i++) {
                            if (strstr($t[$i], 'base64') != '') {
                                $dat += array($f[$i] => base64_decode($ar[$i]));
                            } else {
                                $dat += array($f[$i] => $ar[$i]);
                            }
                        }
                        array_push($data, $dat);
                    }
                }

                /*finish*/
                $res['options'] = $data;
                if ($hd) {
                    $res['heads'] = $heads;
                }
                $res['fields'] = $fields;
                $res['types'] = $types;
                $res['classes'] = $classes;

                $res['pp'] = $pp;
                $res['dpp'] = $dpp;
                $res['page'] = $page;
                $res['total'] = $total;

                $res['add'] = ab_cfg_item('add', $cfg);
                $res['edit'] = $edit;
                $res['editCfg'] = $editCfg;
                $res['editClick'] = $editClick;

                $res['searchSet'] = ($seaSet != '') ? 1 : '';
                $res['search'] = $search;

                $res['orderSet'] = $orderSet;
                $res['order'] = $order;

                $res['filter1Set'] = $filter1Set;
                $res['filter1'] = $filter1;

                $res['filter2Set'] = $filter2Set;
                $res['filter2'] = $filter2;

                $res['filter3Set'] = $filter3Set;
                $res['filter3'] = $filter3;

                if (ab_rights('build')) {
                    $res['query'] = $que;
                }

            } else {
                $err = $ab['p.access'];
            }

        }

        echo ab_result($r, array('data' => $res, 'message' => $err));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_short_rnd($ls)
{
    $t = "2345689ABCDEFGHJKLMNPQRSTUVXYZabcdefghijkmnopqrstuvxyz";
    $f = '';
    for ($i = 0; $i < $ls; $i++) {
        $f .= $t[rand(0, strlen($t) - 1)];
    }
    return $f;
}


function get_client()
{
    global $Q, $ab_user, $ab;

    if ($ab_user['id'] != null) {
        $abs_uid = preg_match('/^[0-9]+$/', $_POST['uid']);
        if ($abs_uid) {
            $abs_uid = (int)$_POST['uid'];
            $count = count_account_client($abs_uid);
            $res = ibadmin_abs::_get_client($abs_uid);

            echo ab_result(1, array('data' => $res, 'count' => $count));
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_uidIsEmpty']));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function search_client()
{
    global $Q, $ab_user, $ab, $ABS_TYPE;
    
    $suggestions = array();

    /* Проверка прав */
    if(ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_'))
    {
        $abs_fio = trim(ab_secure($_GET['fio']));
        if ($abs_fio) {
            $fio = mb_convert_case($_GET['fio'], MB_CASE_UPPER, "utf-8");

            if ($ABS_TYPE == 'mssql') {
                $fio = mb_convert_encoding($fio, 'cp1251');
            } else {
                $fio = '%' . $fio . '%';
            }

            $filterUserType = ab_secure($_GET['ur']) ? Local::FIR_ABS : false;
            $res = ibadmin_abs::_search_client($fio, $filterUserType);
            #$res = ibadmin_abs::_search_client($fio);

            if ($res) {
                foreach ($res as $result) {
                    $exp = explode(" ", $result['DCUSBIRTHDAY']);
                    $result['DCUSBIRTHDAY'] = $exp[0];

                    $suggestions[] = $result['ICUSNUM'] . ' ' . $result['CCUSNAME'] . ' ' . $result['CCUSPASSP_SER'] . ' ' . $result['CCUSPASSP_NUM'] . ' ' . $result['DCUSBIRTHDAY'];
                }
            }

            echo ab_result($suggestions ? 1 : 0, array('suggestions' => $suggestions));
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_fioIsEmpty'], 'suggestions' => $suggestions));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError'], 'suggestions' => $suggestions));
}


function count_account_client($uid)
{
    global $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $res = ibadmin_abs::_accounts_list($uid);
        $count = count($res);
        return $count;

    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function fail_acc_join()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_POST['uid'])) ? ab_secure($_POST['uid']) : '';
        $sql = "DELETE FROM t_ib_users WHERE f_uid = '$uid'";
        $queId = $Q->query($sql);
        echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function check_client_abs()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {

        $icusnum = (isset($_POST['icusnum'])) ? ab_secure(trim($_POST['icusnum'])) : '';
        $cellphone = (isset($_POST['cellphone'])) ? ab_secure(trim($_POST['cellphone'])) : '';

        if(!$icusnum && !$cellphone){
            echo ab_result(0, array('message' => $ab['ibadmin.bank_error.abs_params_empty']));
            exit();
        }

        $sql = "SELECT f_cellphone,f_abs_ids, f_uid  FROM t_ib_users WHERE f_abs_ids='$icusnum'";

        if($cellphone){
            $sql.= " OR f_cellphone='$cellphone'";
        }

        $queId = $Q->query($sql);
		
        if ($queId){
            while ($res = $queId->fetch_assoc()){
                error_log(__METHOD__.print_r($res,1));
                $result[] = $res;
            }
        }

        if (is_array($result)) {
            if ($result[0]['f_cellphone'] == $cellphone && $cellphone != '')
                echo ab_result(0, array('message' => $ab['var.ibadmin.bank_phoneIsset'], 'data' => $row['f_cellphone']));
            else if ($result[0]['f_abs_ids'] == $icusnum)
                echo ab_result(0, array('abs_ids' => $result[0]['f_abs_ids'], 'uid' => $result[0]['f_uid'], 'message' => $ab['var.ibadmin.bank_userIsset']));
        } else
            echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function check_client_phone()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $cellphone = (isset($_POST['cellphone'])) ? ab_secure($_POST['cellphone']) : '';
        $sql = "SELECT f_uid FROM t_ib_users WHERE f_cellphone='$cellphone'";
        $queId = $Q->query($sql);

        if ($queId)
            while ($res = $queId->fetch_assoc())
                $result[] = $res;

        if (is_array($result))
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_phoneIsset'], 'data' => $row['f_cellphone']));
        else
            echo ab_result(1);

    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_get_log_()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $cntrl = 10;
        $page = (isset($_REQUEST['page'])) ? ab_secure($_REQUEST['page']) : 0;
        $uid = (isset($_REQUEST['uid'])) ? ab_secure($_REQUEST['uid']) : '';
        $datebegin = (isset($_REQUEST['datebegin'])) ? date('d.m.Y', strtotime(ab_secure($_REQUEST['datebegin'])) - 86400) : date('d.m.Y', strtotime("-1 DAY"));
        $dateend = (isset($_REQUEST['dateend'])) ? date('d.m.Y', strtotime(ab_secure($_REQUEST['dateend'])) + 86400) : date('d.m.Y', strtotime("+1 DAY"));
        $queId = $Q->query("SELECT f_id, f_tim, f_editor, f_editor_id, f_user_uid, f_operation, f_status, f_note FROM t_ib_editors_log WHERE f_user_uid = '$uid' AND STR_TO_DATE(f_tim, '%d.%m.%Y')>=STR_TO_DATE('$datebegin', '%d.%m.%Y') AND STR_TO_DATE(f_tim, '%d.%m.%Y')<=STR_TO_DATE('$dateend', '%d.%m.%Y') ORDER BY f_tim DESC");

        if ($queId) {
            $key = 0;
            while ($row = $queId->fetch_assoc()) {
                $result[$key] = $row;
                $key++;
            }

            $count = null;
            if (count($result) > $cntrl)
                $count = ceil(count($result) / $cntrl);

            $result = array_slice($result, $page * $cntrl, $cntrl);
        }

        echo ab_result($queId ? 1 : 0, array('data' => $result, 'datebegin' => $datebegin, 'dateend' => $dateend, 'count' => $count, 'active' => $page));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function search_client_be()
{
    global $Q, $ab_user, $ab;

    $suggestions = array();

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        //$abs_fio = preg_match('/^([а-яА-ЯЁё\s]+)$/u', ab_secure($_GET['fio']));
        $abs_fio = ab_secure($_GET['fio']);

        if ($abs_fio) {
            $fio = mb_convert_case(ab_secure($_GET['fio']), MB_CASE_UPPER, "utf-8");
            $res = ibadmin_DB::_search_client_be($fio);

            if ($res)
                foreach ($res as $result)
                    $suggestions[] = $result['f_uid'] . ' ' . base64_decode($result['f_full_name']) . ' ' . $result['f_cellphone'] . ' ' . $result['f_ownerbd'];

            echo ab_result($suggestions ? 1 : 0, array('suggestions' => $suggestions));
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_fioIsEmpty'], 'suggestions' => $suggestions));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError'], 'suggestions' => $suggestions));
}

function search_client_dbo(){
global $Q, $ab_user, $ab;

    $suggestions = array();

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $fio = preg_match('/^([а-яА-ЯЁё\s]+)$/u', ab_secure($_GET['fio']));

        if ($fio) {
            $fio_title = mb_convert_case(ab_secure($_GET['fio']), MB_CASE_TITLE, "utf-8");
			$fio_title = base64_encode($fio_title);
			$fio_upper = mb_convert_case(ab_secure($_GET['fio']), MB_CASE_UPPER, "utf-8");
			$fio_upper_base = base64_encode($fio_upper);
			$sql = "SELECT * FROM t_ib_users WHERE f_full_name LIKE '%$fio_title%' OR f_full_name LIKE '%$fio_upper_base%' OR UPPER(f_sea) LIKE '%$fio_upper%'";
            $queId = $Q->query($sql);
            if ($queId) {
                while ($res = $queId->fetch_assoc()) {
                    $row[] = $res;
                }
				if ($row){
					foreach ($row as $result){
						$suggestions[] = $result['f_uid'] . ' ' . base64_decode($result['f_full_name']) . ' ' . $result['f_cellphone'] . ' ' . $result['f_ownerbd'];
					}
				}
            }
            echo ab_result($suggestions ? 1 : 0, array('suggestions' => $suggestions));
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_fioIsEmpty'], 'suggestions' => $suggestions));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError'], 'suggestions' => $suggestions));	
}

function check_client_be()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $fio = (isset($_POST['fio'])) ? ab_secure($_POST['fio']) : '';
        $cellphone = (isset($_POST['cellphone'])) ? ab_secure($_POST['cellphone']) : '';
        $bd = (isset($_POST['bd'])) ? ab_secure($_POST['bd']) : '';
        $fio = mb_convert_case($fio, MB_CASE_TITLE, "utf-8");
        $res = ibadmin_DB::_check_client_be($fio, $cellphone, $bd);
        $row = ibadmin_DB::_check_client_phone($cellphone);

        if ($row && !$res)
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_phoneIsset']));
        elseif ($res)
            echo ab_result(1, array('data' => $res[0]['f_uid']));
        else
            echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function set_owner_accounts()
{
    global $Q, $ab_user, $ab;

    /* Проверка прав */
    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_POST['uid'])) ? ab_secure($_POST['uid']) : '';
        $icusnum = (isset($_POST['icusnum'])) ? ab_secure($_POST['icusnum']) : '';
        $abs_ids = (isset($_POST['abs_ids'])) ? ab_secure($_POST['abs_ids']) : '';

        $res = ibadmin_DB::_set_owner_accounts($uid, $icusnum, $abs_ids);
        echo ab_result(1);
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function ibadmin_pay_password($uid)
{
    global $ab;

    if (ab_rights('ibadmin_dbo_users_') || ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_POST['uid'])) ? ab_secure($_POST['uid']) : '';

        $mw = ibadmin_short_rnd(8);
        error_log(__METHOD__.' '.$mw);
        $hach = hash('sha512', $mw);

        $queId = ibadmin_DB::_search_user($uid);

        if ($queId) {
            $row = $queId->fetch_assoc();
            $phone = $row['f_cellphone'];

            ibadmin_DB::_set_pay_password($uid, $hach, $mw, $phone);

            $text = str_replace('%p', $mw, $ab['ibadmin.bank_paypass_text_sms']);
            _send_sms($phone, $text);

            echo ab_result(1);
            write_log($uid, "regenPayPassword", 1);
        }
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}


function ibadmin_new_login()
{
    global $ab, $Q;

    if (ab_rights('ibadmin_dbo_users_') or ab_rights('ibadmin_dbo_edit_users_')) {
        $uid = (isset($_POST['uid'])) ? ab_secure($_POST['uid']) : '';

        $uid = (int)$uid;
        $queId = ibadmin_DB::_search_user($uid);

        if ($queId)
		{
            $row = $queId->fetch_assoc();
            $phone = $row['f_cellphone'];
			
			$s1 = substr(base64_decode($row['f_Engname']), 0, 1);
			$s2 = substr(base64_decode($row['f_Englastname']), 0, 5);
			
			$new_login = UserTools::generate_login($s1, $s2);
            ibadmin_DB::_new_login($uid, $new_login, $phone);

            $text = str_replace('%l', $new_login, $ab['ibadmin.bank_login_text_sms']);
            _send_sms($phone, $text);

            echo ab_result(1);
            write_log($uid, "regenLogin", 1);
        } else
            echo ab_result(0, array('message' => $ab['var.ibadmin.bank_userNotFound']));
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function view_user_log()
{
    global $ab, $do;

    if (ab_rights('ibadmin_logs_')) {
        $ab['ibadmin.view.ibadmin_userlog'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['ibadmin.view.ibadmin_userlog']);
        $do = "view";
        ab_view();
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function view_users()
{
    global $ab, $do, $Q;
    $Q->query($ab['ibadmin.table.ibadmin_users']);
    $do = "view";
    ab_view();

}

function view_user_log_day()
{
    global $ab, $do;

    if (ab_rights('ibadmin_logs_')) {
		switch ($_GET['table']){
			case 'iblog_day_all':
				$ab['iblog.view.iblog_day_all'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_all']);
				break;
				
			case 'iblog_userlog_day':
				$ab['iblog.view.iblog_userlog_day'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_userlog_day']);
				break;
			
			case 'iblog_day_oper':
				$ab['iblog.view.iblog_day_oper'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_oper']);
				break;
				
			case 'iblog_day_step':
				$ab['iblog.view.iblog_day_step'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_step']);
				$ab['iblog.view.iblog_day_step'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_day_step']);
				break;
				
			case 'iblog_day_action':
				$ab['iblog.view.iblog_day_action'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_action']);
				$ab['iblog.view.iblog_day_action'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_day_action']);
				break;
			case 'iblog_day_action_succesfull':
				$ab['iblog.view.iblog_day_action_succesfull'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_action_succesfull']);
				$ab['iblog.view.iblog_day_action_succesfull'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_day_action_succesfull']);
				break;
			case 'iblog_day_action_fail':
				$ab['iblog.view.iblog_day_action_fail'] = str_replace('$day', ab_secure($_GET['day']), $ab['iblog.view.iblog_day_action_fail']);
				$ab['iblog.view.iblog_day_action_fail'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_day_action_fail']);
				break;
		}
        $do = "view";
        ab_view();
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function view_user_log_month()
{
    global $ab, $do;

    if (ab_rights('ibadmin_logs_')) {
		switch ($_GET['table']){
			case 'iblog_userlog_month':
				$ab['iblog.view.iblog_userlog_month'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_userlog_month']);
				break;
				
			case 'iblog_month_all':
				$ab['iblog.view.iblog_month_all'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_all']);
				break;
				
			case 'iblog_month_oper':
				$ab['iblog.view.iblog_month_oper'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_oper']);
				break;
			
			case 'iblog_month_step':
				$ab['iblog.view.iblog_month_step'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_step']);
				$ab['iblog.view.iblog_month_step'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_month_step']);
				break;
				
			case 'iblog_month_action':
				$ab['iblog.view.iblog_month_action'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_action']);
				$ab['iblog.view.iblog_month_action'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_month_action']);
				break;
			case 'iblog_month_action_succesfull':
				$ab['iblog.view.iblog_month_action_succesfull'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_action_succesfull']);
				$ab['iblog.view.iblog_month_action_succesfull'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_month_action_succesfull']);
				break;
			case 'iblog_month_action_fail':
				$ab['iblog.view.iblog_month_action_fail'] = str_replace('$month', ab_secure($_GET['month']), $ab['iblog.view.iblog_month_action_fail']);
				$ab['iblog.view.iblog_month_action_fail'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_month_action_fail']);
				break;
		}
        $do = "view";
        ab_view();
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function view_user_log_all()
{
    global $ab, $do;

    if (ab_rights('ibadmin_logs_')) {
		switch ($_GET['table']){
			case 'iblog_userlog':
				$ab['iblog.view.iblog_userlog'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['iblog.view.iblog_userlog']);
				$ab['iblog.view.iblog_userlog'] = str_replace('$filter1', ab_secure($_GET['filter1']), $ab['iblog.view.iblog_userlog']);
				$ab['iblog.view.iblog_userlog'] = str_replace('$filter2', ab_secure($_GET['filter2']), $ab['iblog.view.iblog_userlog']);
				break;
				
			case 'iblog_user_oper':
				$ab['iblog.view.iblog_user_oper'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['iblog.view.iblog_user_oper']);
				$ab['iblog.view.iblog_user_oper'] = str_replace('$filter1', ab_secure($_GET['filter1']), $ab['iblog.view.iblog_user_oper']);
				$ab['iblog.view.iblog_user_oper'] = str_replace('$filter2', ab_secure($_GET['filter2']), $ab['iblog.view.iblog_user_oper']);
				break;
			
			case 'iblog_user_action':
				$ab['iblog.view.iblog_user_action'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['iblog.view.iblog_user_action']);
				$ab['iblog.view.iblog_user_action'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_user_action']);
				$ab['iblog.view.iblog_user_action'] = str_replace('$filter1', ab_secure($_GET['filter1']), $ab['iblog.view.iblog_user_action']);
				$ab['iblog.view.iblog_user_action'] = str_replace('$filter2', ab_secure($_GET['filter2']), $ab['iblog.view.iblog_user_action']);
				break;
			case 'iblog_user_action_succesfull':
				$ab['iblog.view.iblog_user_action_succesfull'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['iblog.view.iblog_user_action_succesfull']);
				$ab['iblog.view.iblog_user_action_succesfull'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_user_action_succesfull']);
				$ab['iblog.view.iblog_user_action_succesfull'] = str_replace('$filter1', ab_secure($_GET['filter1']), $ab['iblog.view.iblog_user_action_succesfull']);
				$ab['iblog.view.iblog_user_action_succesfull'] = str_replace('$filter2', ab_secure($_GET['filter2']), $ab['iblog.view.iblog_user_action_succesfull']);
				break;
			case 'iblog_user_action_fail':
				$ab['iblog.view.iblog_user_action_fail'] = str_replace('$uid', ab_secure($_GET['uid']), $ab['iblog.view.iblog_user_action_fail']);
				$ab['iblog.view.iblog_user_action_fail'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_user_action_fail']);
				$ab['iblog.view.iblog_user_action_fail'] = str_replace('$filter1', ab_secure($_GET['filter1']), $ab['iblog.view.iblog_user_action_fail']);
				$ab['iblog.view.iblog_user_action_fail'] = str_replace('$filter2', ab_secure($_GET['filter2']), $ab['iblog.view.iblog_user_action_fail']);
				break;
			case 'iblog_user_account':
				$ab['iblog.view.iblog_user_account'] = str_replace('$account', ab_secure($_GET['account']), $ab['iblog.view.iblog_user_account']);
				break;
			case 'iblog_account_action':
				$ab['iblog.view.iblog_account_action'] = str_replace('$account', ab_secure($_GET['account']), $ab['iblog.view.iblog_account_action']);
				$ab['iblog.view.iblog_account_action'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_account_action']);
				break;
			case 'iblog_account_action_fail':
				$ab['iblog.view.iblog_account_action_fail'] = str_replace('$account', ab_secure($_GET['account']), $ab['iblog.view.iblog_account_action_fail']);
				$ab['iblog.view.iblog_account_action_fail'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_account_action_fail']);
				break;
			case 'iblog_account_action_succesfull':
				$ab['iblog.view.iblog_account_action_succesfull'] = str_replace('$account', ab_secure($_GET['account']), $ab['iblog.view.iblog_account_action_succesfull']);
				$ab['iblog.view.iblog_account_action_succesfull'] = str_replace('$action', ab_secure($_GET['action']), $ab['iblog.view.iblog_account_action_succesfull']);
				break;
		}
        $do = "view";
        ab_view();
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function search_account()
{
    global $ab, $Q, $abun;

	$suggestions = array();
    if (ab_rights('ibadmin_logs_')) {
		$account = (isset($_GET['account'])) ? (ab_secure($_GET['account'])) : '';
		if ($account){
			$queId = $Q->query("SHOW FULL TABLES LIKE 't_ib_user_log_account_$account%'");
			if ($queId){
				while ($row = $queId->fetch_assoc()) {
					$acc = explode('_', $row["Tables_in_$abun (t_ib_user_log_account_$account%)"]);
                    $suggestions[] = $acc[5];
				}				
			}                
		}
		echo ab_result($suggestions ? 1 : 0, array('suggestions' => $suggestions));
		
    } else
        echo ab_result(0, array('message' => $ab['var.ibadmin.bank_authError']));
}

function openFreeMessFile()
{
    global $Q, $abhost, $abbs, $abun, $abps, $ab;

    $docId = ab_secure($_GET['doc']);
    $fileId = ab_secure($_GET['fileid']);

    $docId = trim(ab_secure($docId));
    $sql = "SELECT f_clientid FROM t_ib_freemess where f_doc = '{$docId}'";
    $rc = $Q->query($sql);
    $rr = $rc->fetch_assoc();
    $uid = $rr['f_clientid'];

    $fm = new FreeMessFile($uid);
    $fm->setPdo(new PDO('mysql:host=' . $abhost . ';dbname=' . $abbs, $abun, $abps, array(
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
    )));

    $fm = $fm->load($uid, $fileId);

    if ($fm)
    {
        $finfo = new finfo(FILEINFO_MIME);
        $type = $finfo->file($fm->getPath());

    $isImage = false;
    $imagesMimeTypes = explode(',', $ab['ibadmin.freemess_inline_open']);
    foreach ($imagesMimeTypes as $imageMimeType) {
        if (stripos($type, $imageMimeType) !== false) {
            $isImage = true;
            break;
        }
    }

    if ($isImage) {
        header('Content-Disposition: inline; filename="' . $fm->getOriginName() . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $fm->getOriginName() . '"');
    }

        header("Content-type: {$type}");
        echo file_get_contents($fm->getPath());
    }
}


function joinCompanyToToken()
{
    global $do, $ab_user;
    $do = 'writeit';
    $r = ab_edit_write();
    //write_log
    $uid = ab_secure($_GET['filter3']);
    write_log($uid, 'joinCompanyToToken', (bool)$r, array(
        'post' => $_POST,
        'get' => $_GET,
        'server' => $_SERVER,
        'cookie' => $_COOKIE
    ));
}

function manageTokenKey()
{
    global $do, $ab_user, $Q;

    $uid = ab_secure($_GET['filter3']);
    $new_stat = ab_secure($_GET['new_stat']);
    $token_id = ab_secure($_GET['id']);

    if ($_FILES['tokenKeyUpload']) {

        $tokenKeyUpload = $_FILES['tokenKeyUpload'];
        $cert = file_get_contents($tokenKeyUpload['tmp_name']);

        $arr = openssl_x509_parse($cert);
        $valid_to = date("Y-m-d H:i:s", $arr['validTo_time_t']);

        $cert = base64_encode($cert);
        $sql = "UPDATE t_rutoken_user_keys_{$uid} SET `f_c0`='1', `f_t3`='{$cert}' WHERE `f_id`='{$token_id}'";
        $Q->query($sql);

        $post = [
            'f_stat' => $new_stat,
            'f_date_exp' => '2016-12-18',
            'uid' => $uid,
            'cfg' => 'ibadmin_user_keys_admin',
            'id' => $token_id,
            'f_date_exp' => $valid_to
        ];

        $_POST = $post;

        $do = 'writeit';
        $r = ab_edit_write(0);

        write_log($uid, 'manageTokenKeyUploadCert', (bool)$r, array(
            'post' => $_POST,
            'get' => $_GET,
            'server' => $_SERVER,
            'cookie' => $_COOKIE
        ));
    }
    else
    {
        $do = 'writeit';
        $r = ab_edit_write(0);

        write_log($uid, 'manageTokenKey', (bool)$r, array(
            'post' => $_POST,
            'get' => $_GET,
            'server' => $_SERVER,
            'cookie' => $_COOKIE
        ));
    }
}

function createTokenKey()
{
    global $do, $ab_user;
    $do = 'writeit';
    $r = ab_edit_write();

    $uid = ab_secure($_GET['search']);

    write_log($uid, 'createTokenKey', (bool)$r, array(
        'post' => $_POST,
        'get' => $_GET,
        'server' => $_SERVER,
        'cookie' => $_COOKIE
    ));
}

function manageToken()
{
    global $do, $ab_user;
    $do = 'writeit';
    $r = ab_edit_write(0);

    $uid = ab_secure($_GET['filter3']);
    write_log($uid, 'manageToken', (bool)$r, array(
        'post' => $_POST,
        'get' => $_GET,
        'server' => $_SERVER,
        'cookie' => $_COOKIE
    ));
}

function freeMessFilesChange()
{
    global $Q, $abhost, $abbs, $abun, $abps, $ab, $ib_user, $ab_user;

    $docId = ab_secure($_POST['doc']);
    $docId = trim(ab_secure($docId));
    $sql = "SELECT f_clientid FROM t_ib_freemess where f_doc = '{$docId}'";
    $rc = $Q->query($sql);
    $rr = $rc->fetch_assoc();
    $uid = $rr['f_clientid'];

    try {
        foreach ($_POST as $var => $value) {
            if (stripos($var, 'file_open') !== false) {
                $fileId = str_replace('file_open_', '', $var);

                $fm = new FreeMessFile($uid);
                $fm->setPdo(new PDO('mysql:host=' . $abhost . ';dbname=' . $abbs, $abun, $abps, array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
                )));

                $fm = $fm->load($uid, $fileId);
                $fm->setIsCanOpen($value);
                $fm->save();


                $log = array();
                $log['action'] = 'freeMessFileChangeOpen';
                $log['uid'] = $uid;
                $log['post']['fileId'] = $fileId;
                $log['post']['operator_uid'] = $ab_user['id'];
                $log['post']['operation'] = $value ? 'set open' : 'set close';
                #write_log($ab_user['id'], 'freeMessFileChangeOpen', 1, $log);
                write_log($uid, 'freeMessFileChangeOpen', 1, $log);

            }

            if (stripos($var, 'file_checked') !== false) {
                $fileId = str_replace('file_checked_', '', $var);

                $fm = new FreeMessFile($uid);
                $fm->setPdo(new PDO('mysql:host=' . $abhost . ';dbname=' . $abbs, $abun, $abps, array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
                )));

                $fm = $fm->load($uid, $fileId);
                $fm->setIsChecked($value);
                $fm->save();

                $log = array();
                $log['uid'] = $uid;
                $log['action'] = 'freeMessFileChangeChecked';
                $log['post']['fileId'] = $fileId;
                $log['post']['operator_uid'] = $ab_user['id'];
                $log['post']['operation'] = $value ? 'set checked' : 'set NOT checked';
            }
        }

        echo ab_result(1, array('message' => $ab['var.p.success'], 'close' => 1));
        exit();
    } catch (Exception $e) {
        echo ab_result(0, array('message' => $ab['var.p.fail']));
        exit();
    }
}


function checkToken()
{
    global $Q, $ab_user, $ab;

    if (ab_rights('ibadmin_token_')) {

        $text = ab_secure($_POST['text']);
        $signature = ab_secure($_POST['hash']);
        #$signature = preg_replace('/[^a-zA-Z0-9]/', '', $signature);
        $key = ab_secure($_POST['key']);
        #$key = base64_decode($key);

        require_once __DIR__ . '/api/extends/token-be-lib.php';


        if (strlen($signature) != 128) {
	    echo ab_result(false, array('error' => 'The signature POST field has to contain exactly 128 non-space characters'));
            exit();
        }

        $r_ecp = substr($signature, 0, 64);
        $s_ecp = substr($signature, 64);

        if (!$key) {
            echo ab_result(false, array('error' => 'no key'));
            exit();
        }

        $x_pkey = substr($key, 0, 64);;
        $y_pkey = substr($key, 64);;

        $text = hash('sha256', $text);
        $check_result = SignatureVerificator::verify($text, $x_pkey, $y_pkey, $r_ecp, $s_ecp);

        if (!$check_result) {
            echo ab_result(false, array('error' => $ab['var.ibadmin.bank_errorSignSign']));
            exit();
        } else {
            echo ab_result(true, array('sign' => 'sign'));
            exit();
        }
    } else {
        echo ab_result(false, array('error' => 'wrong rights'));
        exit();
    }
}

function get_cert_requert(){
    global $Q;

    $token_id = ab_secure($_REQUEST['token_id']);
    $user_id = ab_secure($_REQUEST['user_id']);

    $sql = "SELECT f_t0, f_name FROM t_rutoken_user_keys_{$user_id} WHERE f_id = '{$token_id}'";
    $rc = $Q->query($sql);

    if ($rc) {
        $row = $rc->fetch_assoc();

        if (isset($row['f_t0']) and $row['f_t0']) {

            $data = base64_decode($row['f_t0']);
            $search = ['-----BEGIN CERTIFICATE REQUEST-----', '-----END CERTIFICATE REQUEST-----'];
            $data = trim(str_replace($search, '', $data));

            $name = base64_decode($row['f_name']);
            $name = ($name!='') ? $name : 'cert_request';


            if (strlen($data)!=0) {
                header("Content-type: application/octet-stream");
                header("Content-Disposition: attachment; filename={$name}.p10");
                echo $data;
            }
            else {
                echo ab_result(0);
            }
        }
    }
}



switch ($do) {
    case 'act_deact':
        ibadmin_act_deact();
        break;
    case 'check_user':
        ibadmin_check_user();
        break;
    case 'new_login':
        ibadmin_new_login();
        break;
    case 'journal':
        ibadmin_users_logs();
        break;
    case 'search_journal':
        ibadmin_search_users_logs();
        break;
    case 'search_log':
        ibadmin_users_logs();
        break;
    case 'user_block':
        ibadmin_user_block();
        break;
    case 'get_tasks':
        ibadmin_get_tasks();
        break;
    case 'get_tasks_date':
        ibadmin_get_tasks_date();
        break;
    case 'get_users_dbo':
        ibadmin_get_users_dbo();
        break;
    case 'set_task':
        ibadmin_set_task();
        break;
    case 'task_analitic_status':
        ibadmin_task_analitic_status();
        break;
    case 'task_auto_update':
        ibadmin_task_auto_update();
        break;
    case 'test_xml':
        test_xml();
        break;
    case 'editors_stat':
        ibadmin_editors_statistics();
        break;
    case 'is_dir':
        ibadmin_is_dir();
        break;
    case 'search_adr':
        ibadmin_search();
        break;
    case 'view':
        ibadmin_view();
        break;
    case 'search_fio':
        ibadmin_search_fio();
        break;
    case 'pay_password':
        ibadmin_pay_password();
        break;
    case 'search_client':
        search_client();
        break;
    case 'get_client':
        get_client();
        break;
    case 'get_uid':
        get_uid();
        break;
    case 'fail_acc_join':
        fail_acc_join();
        break;
    case 'check_client_abs':
        check_client_abs();
        break;
    case 'check_client_phone':
        check_client_phone();
        break;
    case 'get_log':
        ibadmin_get_log();
        break;
    case 'set_log':
        ibadmin_set_log();
        break;
    case 'search_client_be':
        search_client_be();
        break;
    case 'check_client_be':
        check_client_be();
        break;
    case 'set_owner_accounts':
        set_owner_accounts();
        break;
    case 'view_user_log':
        view_user_log();
        break;
	case 'search_account':
        search_account();
        break;
	case 'search_client_dbo':
        search_client_dbo();
        break;
    // in iadmin_c.php
    case 'write_new_client':
        write_new_client();
        break;
    case 'join' :
        join_acc();
        break;

    case 'account_rights' :
        account_rights();
        break;

    case 'get_accounts_all':
        get_accounts_all();
        break;
    case 'get_accounts':
        get_accounts();
        break;
    case 'get_accounts_join':
        get_accounts_join();
        break;

    case 'view_users':
        view_users();
        break;

    case 'change_rights':
        change_rights();
        break;
		
	case 'view_user_log_day':
        view_user_log_day();
        break;

    case 'view_user_log_month':
        view_user_log_month();
        break;
		
	case 'view_user_log_all':
        view_user_log_all();
        break;

    case 'getClientInfo2':
        new_fizclient();
        break;

    case 'getClientInfo2AbsId':
        getfizclientinfo();
        break;

    case 'new_non_client':
        new_non_client();
        break;

    case 'non_client_join_abs':
        non_client_join_abs();
        break;

    case 'save_non_client':
        save_non_client();
        break;

    case 'refresh_from_abs':
        refresh_from_abs();
        break;

    case 'openFreeMessFile':
        openFreeMessFile();
        break;
    case 'freeMessFilesChange':
        freeMessFilesChange();
	    break;

    case 'joinCompanyToToken':
        joinCompanyToToken();
        break;
    case 'manageTokenKey':
        manageTokenKey();
        break;
    case 'createTokenKey':
        createTokenKey();
        break;
    case 'manageToken':
        manageToken();
        break;
    case 'checkToken':
        checkToken();
        break;

    case 'getCertRequest':
        get_cert_requert();
        break;
}



?>
