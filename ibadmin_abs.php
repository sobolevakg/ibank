<?php

class ibadmin_abs
{
    /**
     *    Запрос списка счетов
     *
     *    Входные параметры:
     *                        $abs_uid - внутренний id клиента в АБС
     */
    /********************************************************************************************/
    /*									Result Set												*/
    /********************************************************************************************/
    /*	ACCOUNT 				- номер счета													*/
    /*  CURRCODE 				- цифровой код валюты счета										*/
    /*  CURRISO 				- текстовый код валюты счета									*/
    /*  CONTRACTID 				- ID договора													*/
    /*  BRANCHID 				- банк (филиал доп.офис)										*/
    /*  BIC 					- БИК															*/
    /*  NAME 					- name дополнительная информация по счету						*/
    /*  ACCOUNTSUBTYPE 			- подтип счета													*/
    /*  STATUS 					- статус счета													*/
    /*  DATESTART 				- дата открытия счета											*/
    /*  STARTDATE 				- дата заключения контракта										*/
    /*  DATEEND 				- дата закрытия счета											*/
    /*  EXPDATE 				- дата закрытия контракта										*/
    /*  REST 					- остаток на счете												*/
    /*  LIMIT 					- неснижаемый остаток											*/
    /*  MINAMOUNT 				- минимальная сумма пополнения/погашения						*/
    /*  COURSE 					- процентная ставка												*/
    /*  AVAILAMOUNTONCARDS 		- средства доступные для списания с привязанных к счету карт	*/
    /*  BLOCKEDAMOUNT 			- сумма заблокированных средств									*/
    /*  CHARGESAMOUNTFORMONTH 	- сумма начислений на счет за текущий месяц						*/
    /*  RESTPP 					- sum планируемый остаток										*/
    /*  RESTRUR 				- суммы заблокированных средств уже учтены здесь				*/
    /*  CARDTYPEID 				- accTypeтип счета												*/
    /*  DATEREST 				- дата запроса													*/
    /*  ACCOUNTTYPE 			- цифровой тип счета (не заполнен)								*/
    /*  CN 						-  																*/
    /********************************************************************************************/

    static function _accounts_list($abs_uid, $prepare = 0)
    {
        try {
            $abs_db = AbsDb::getInstance('oci');

            $obAccList = new AbsProcAccountsList(array($abs_uid, ''));
            $in_params = $obAccList->get_in_params();
            $res = $abs_db->request($in_params['str_query'], $in_params['params'], $in_params['query_type']);

            if ($prepare) {
                $adapter = new AdapterAccListIbadmin();
                $res = $adapter->processAbsResult($res);
            }

            return $res;

        } catch (Exception $e) {
            error_log(__METHOD__ . ' oci' . $e->getMessage());
            return [];
        } catch (SystemException $e) {
            error_log(__METHOD__ . ' oci' . $e->getMessage());
            return [];
        }

    }

    static function _accounts_list_fiz($abs_uid)
    {
        global $fiz_test, $fiz_test_key;

        try {
            $abs_db = AbsDb::getInstance('oci_fiz');

            $obAccList = new AbsFiziki(array($abs_uid), 'getacclist2');
            $in_params = $obAccList->get_in_params();

            if (isset($fiz_test) && $fiz_test) {
                $fiz_test_key = 'getacclistibadmin';
            }

            $res = $abs_db->request($in_params['str_query'], $in_params['params'], $in_params['query_type']);

            if ($res) {
                $res = $obAccList->processAbsResult($res, 'getacclistibadmin');
            }

            return $res;

        } catch (Exception $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        } catch (SystemException $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        }

    }


    static function _accounts_list_fiz2($abs_uid)
    {
        global $fiz_test, $fiz_test_key;

        try {

            $abs_db = AbsDb::getInstance('oci_fiz');

            $obAccList = new AbsFiziki(array($abs_uid), 'getacclist2');
            $in_params = $obAccList->get_in_params();

            if (isset($fiz_test) && $fiz_test) {
                $fiz_test_key = 'getacclistibadmin';
            }

            $res = $abs_db->request($in_params['str_query'], $in_params['params'], $in_params['query_type']);

            if ($res) {
                $res = $obAccList->processAbsResult($res, 'getacclistibadmin2');
            }

            return $res;

        } catch (Exception $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        } catch (SystemException $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        }
    }

    static function _account($abs_uid, $acc)
    {
        try {
            $abs_db = AbsDb::getInstance('oci');

            $obAccList = new AbsProcAccountsList(array($abs_uid, $acc));
            $in_params = $obAccList->get_in_params();
            $res = $abs_db->request($in_params['str_query'], $in_params['params'], $in_params['query_type']);

            $adapter = new AdapterAccListIbadmin();
            $res = $adapter->processAbsResult($res);

            return $res;

        } catch (Exception $e) {
            error_log(__METHOD__ . ' oci' . $e->getMessage());
            return [];
        } catch (SystemException $e) {
            error_log(__METHOD__ . ' oci' . $e->getMessage());
            return [];
        }

    }

    static function _account_fiz($abs_uid, $cur_acc)
    {
        try {
            $accs = self::_accounts_list_fiz($abs_uid);
            $res = [];
            if ($accs) {
                foreach ($accs as $acc) {
                    if ($acc['account'] == $cur_acc) {
                        $res[] = $acc;
                        return $res;
                    }
                }
            }

            return $res;
        } catch (Exception $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        } catch (SystemException $e) {
            error_log(__METHOD__ . ' oci_fiz' . $e->getMessage());
            return [];
        }

    }

    /**
     *    Запрос данных пользователя
     *
     *    Входные параметры:
     *                        $abs_uid - внутренний id клиента в АБС
     */
    static function _get_client($abs_uid, $proc = 0)
    {

        set_abs_conf('oci');
        $abs_db = AbsDb::getInstance('oci');

        $obCust = new AbsSelectCus('getClient', array($abs_uid));
        $params_in = $obCust->get_in_params();
        $res = $abs_db->request($params_in['str_query'], $params_in['params'], $params_in['query_type']);

        if ($proc) {
            $res = $obCust->processAbsResult('getClient', $res);
        }

        return $res;

        /********************************************************************************************/
        /*									Result Set												*/
        /********************************************************************************************/
        /* ICUSNUM 				- уникальный номер клиента											*/
        /* CCUSFLAG 			- тип клиента														*/
        /* DCUSBIRTHDAY 		- дата рождения (для физ. лиц)										*/
        /* CCUSADDR_ENGLISH		- адрес на английском языке											*/
        /* CCUSNAME 			- английское название клиента										*/
        /* CCUSLAST_NAME 		- фамилия															*/
        /* CCUSFIRST_NAME 		- имя																*/
        /* CCUSMIDDLE_NAME		- отчество															*/
        /* CCUSNAME_SH 			- краткое наименование клиента										*/
        /* CCUSNAME				- название клиента													*/
        /* CCUSNUMNAL 			- ИНН																*/
        /* CCUSREZ 				- резиденство														*/
        /* DCUSEDIT 			- дата окончания срока действия документов							*/
        /* DCUSOPEN 			- дата заведения клиента											*/
        /* CCUSIDOPEN 			- кто завел клиента													*/
        /********************************************************************************************/
    }


    /**
     *    Поиск пользователя
     *
     *    Входные параметры:
     *                        $request - строка для поиска по фио
     */
    static function _search_client($request, $filterUserType = false)
    {
        set_abs_conf('oci');
        $abs_db = AbsDb::getInstance('oci');

        if ($filterUserType) {
            $obCust = new AbsSelectCus('searchClientUr', array($request, Local::FIR_ABS));
        } else {
            $obCust = new AbsSelectCus('searchClient', array($request));
        }


        $params_in = $obCust->get_in_params();

        // file_put_contents('/tmp/err.txt', $params_in['str_query'] . print_r($params_in, 1));
        $res = $abs_db->request($params_in['str_query'], $params_in['params'], $params_in['query_type']);

        return $res;

        /********************************************************************************************/
        /*									Result Set												*/
        /********************************************************************************************/
        /* ICUSNUM 				- уникальный номер клиента											*/
        /* CCUSFLAG 			- тип клиента														*/
        /* DCUSBIRTHDAY 		- дата рождения (для физ. лиц)										*/
        /* CCUSADDR_ENGLISH		- адрес на английском языке											*/
        /* CCUSNAME 			- английское название клиента										*/
        /* CCUSLAST_NAME 		- фамилия															*/
        /* CCUSFIRST_NAME 		- имя																*/
        /* CCUSMIDDLE_NAME		- отчество															*/
        /* CCUSNAME_SH 			- краткое наименование клиента										*/
        /* CCUSNAME				- название клиента													*/
        /* CCUSNUMNAL 			- ИНН																*/
        /* CCUSREZ 				- резиденство														*/
        /* DCUSEDIT 			- дата окончания срока действия документов							*/
        /* DCUSOPEN 			- дата замедения клиента											*/
        /* CCUSIDOPEN 			- кто завел клиента													*/
        /********************************************************************************************/
    }

    /**
     *    Поиск пользователя
     *
     *    Входные параметры:
     *                        $request - строка для поиска по фио
     */
    static function _search_client2($request)
    {
        global $fiz_test, $fiz_test_key;

        set_abs_conf('oci_fiz'); // переподключаемся к абс физиков
        $abs_db = AbsDb::getInstance('oci_fiz');

        // быстрый поиск
        $obCust = new AbsFiziki($request, 'fiziki_getClientABSquik');
        $params_in = $obCust->get_in_params();
        //file_put_contents('/tmp/err.txt', $params_in['str_query'] . print_r($params_in, 1));

        if (isset($fiz_test) && $fiz_test) {
            $fiz_test_key = 'fiziki_getClientABSquik';
        }
        $res = $abs_db->request($params_in['str_query'], $params_in['params'], $params_in['query_type']);

        if (!$res) {
            throw new SystemException(__METHOD__ . 'request failed');
        }

        $res = $obCust->processAbsResult($res, 'fiziki_getClientABSquik');


        return $res;

        /********************************************************************************************/
        /*									Result Set												*/
        /********************************************************************************************/
        /* ICUSNUM 				- уникальный номер клиента											*/
        /* CCUSFLAG 			- тип клиента														*/
        /* DCUSBIRTHDAY 		- дата рождения (для физ. лиц)										*/
        /* CCUSADDR_ENGLISH		- адрес на английском языке											*/
        /* CCUSNAME 			- английское название клиента										*/
        /* CCUSLAST_NAME 		- фамилия															*/
        /* CCUSFIRST_NAME 		- имя																*/
        /* CCUSMIDDLE_NAME		- отчество															*/
        /* CCUSNAME_SH 			- краткое наименование клиента										*/
        /* CCUSNAME				- название клиента													*/
        /* CCUSNUMNAL 			- ИНН																*/
        /* CCUSREZ 				- резиденство														*/
        /* DCUSEDIT 			- дата окончания срока действия документов							*/
        /* DCUSOPEN 			- дата замедения клиента											*/
        /* CCUSIDOPEN 			- кто завел клиента													*/
        /********************************************************************************************/
    }

    /**
     *    Поиск пользователя  по дате рождения и номеру телефона
     *
     */
    static function _search_client_by_phone_and_bd($params)
    {
        set_abs_conf('oci');
        $abs_db = AbsDb::getInstance('oci');

        $obCust = new AbsSelectCus('searchClientByPhoneAndBirthDay', $params);
        $params_in = $obCust->get_in_params();
        $res = $abs_db->request($params_in['str_query'], $params_in['params'], $params_in['query_type']);
        $r = $obCust->processAbsResult('searchClientByPhoneAndBirthDay', $res);

        return $r;

    }

}
