<?php

$node_name      = '';
$node_end       = 0;
$insert_hash    = array();
$currency_array = array();
$data_array     = array ();

//-------data_sourse_settings---
$file      = './cache/data.xml';
$life_time = 360;
$url       = 'http://www.cbr.ru/scripts/XML_daily.asp';
$templates = 'tpl';
//------------------------------

//--------mysql_settings--------
$mysql_server   = "localhost";
$mysql_user     = "more";
$mysql_password = "more";
$mysql_db       = "more";
//-----------------------------

set_variable("cmd");
set_variable("currency");


//------------connect_to_mysql----
if (!mysql_pconnect($mysql_server, $mysql_user, $mysql_password))
     trigger_error("Cannot connect to the database. ".mysql_error(),E_USER_ERROR);
if (!mysql_select_db($mysql_db))
     trigger_error("Cannot select database. ".mysql_error(),E_USER_ERROR);
//-------------------------------

    switch ($cmd) {
        case 'set_currency':
            set_currency($currency);
            break;
        case 'get_data':
            get_data();
            break;
        default:
            init();
    }

//=============================
//отдаем данные по валютам в формате json, но так как на стороне клиента нам не хочется 
//заниматься парсингом в JS то это не json а пародия на него, мы отдаем сразу кусок HTML
//для вставки в тело документа.
//=============================
function get_data(){

    global $insert_hash;
    global $currency_array;
    global $data_array;

    array_push($data_array, '<table><tr><td>Currency</td><td>Code</td><td>Nominal</td><td>Value</td></tr>');

    //фильтр для отображения только выбранных валют
    $query = "select char_code from test_currency where is_active = 1";
    $result = mysql_query($query) or die("ERROR:".mysql_error());
    while($sql_data = mysql_fetch_array($result)){
        array_push($currency_array, $sql_data['char_code']);
    }
    mysql_free_result($result);

    //используем парсер, хотя такие простые данные было бы проще разобрать регулярными выражениями
    $XMLparser = xml_parser_create();

    xml_set_element_handler($XMLparser, 'startElement', 'endElement');
    xml_set_character_data_handler($XMLparser, 'stringElement');

    $data = get();

    if (!xml_parse($XMLparser, $data)) { die('Ошибка обработки данных'); }

    xml_parser_free($XMLparser);

    header('Content-Type: application/x-javascript; charset=utf8');
    array_push($data_array, '</table>');

    $out = array("data" => join("\n", $data_array));
    print json_encode($out);
    exit;
}

//выбираем названия элементов, игнорируя закрывающие элементы
//сразу фильтруем по валютам
function stringElement($parser, $str) {

    global $node_name;
    global $node_end;
    global $insert_hash;
    global $currency_array;
    global $data_array;

    if($node_end){$node_end = 0; return;}

    $str = trim($str);

    switch ($node_name) {
        case 'VALUE':
            if( array_search($insert_hash['CHARCODE'], $currency_array) === FALSE ){ return;}
            $insert_hash[$node_name] = $str;
            array_push($data_array, '<tr><td>'.$insert_hash['NAME'].'</td><td>'.$insert_hash['CHARCODE'].'</td><td>'.$insert_hash['NOMINAL'].'</td><td>'.$insert_hash['VALUE'].'</td></tr>');
            break;
        default:
            $insert_hash[$node_name] = $str;
    }
}

//нужно дабы получить имя элемента
function startElement($parser, $name) {
    global $node_name;
    $node_name = $name;
}

//нужно что бы пропустить закрывающий элемент
function endElement($parser, $name) {
    global $node_end;
    $node_end = 1;
}

//=============================

//=============================
//заносим в базу данных флажок о том что валюта выбрана к показу
//=============================
function set_currency($currency){

    $currency = mysql_real_escape_string($currency);

    $query =  "INSERT INTO test_currency SET char_code='$currency', is_active = NOT(is_active) ON DUPLICATE KEY UPDATE is_active = NOT(is_active)";
    mysql_query($query) or die("ERROR :".mysql_error());

    get_data();
}
//=============================

//=============================
//начало работы. отрисовываем страницу, получаем данные о валютах
//сразу делаем что то типа административной панели, для выбора фильтра
//что бы не делать отдельную админку. Тут парсим регулярными выражениями
//=============================
function init(){

  $variable_array = array();
  $data_array = array ();

  $data = get();

  if($data !== FALSE){
      foreach (split("\n", $data) as $str){
          if(preg_match("/<CharCode>(\w+)<\/CharCode>/i", $str, $matches))
          {
             $query = "select is_active from test_currency where char_code like '$matches[1]'";
             $result = mysql_query($query) or die("ERROR:".mysql_error());
             $sql_data = mysql_fetch_array($result);
             mysql_free_result($result);

             if($sql_data['is_active']){
                 array_push($data_array, '<tr><td>'.$matches[1].'</td><td><input type="checkbox" id="'.$matches[1].'" checked onChange="set_currency(\''.$matches[1].'\');"></td></tr>');
             }
             else{
                 array_push($data_array, '<tr><td>'.$matches[1].'</td><td><input type="checkbox" id="'.$matches[1].'" onChange="set_currency(\''.$matches[1].'\');"></td></tr>');
             }
          }
     }
  }

  $variable_array['set_currency_filter'] = join("\n", $data_array);
  $content = get_content('main.tpl', $variable_array);

  print $content;
  exit;
}
//=============================

//##################################################################
//------------------------------------------------------------
function get_content($template_name, $variable_array){
    global $templates;

    $data = file_get_contents($templates.'/'.$template_name);
    foreach($variable_array as $key => $value){
        $pattern = '<!--!'.$key.'-->';
        $data = str_replace($pattern, $value, $data);
       }
    return($data);
}
//------------------------------------------------------------

//работа с кэшем, получение данных и сохранение.
//------------------------------------------------------------
function get() {
  global $life_time;
  global $file;
  global $url;

  if (!file_exists($file)) {
      $data = file_get_contents($url);
      if($data){
          save($data);
          return $data;
      }
  }
  else{
      if( $life_time > time() - filemtime($file) ){
          $data = file_get_contents($file);
      }
      else{
          $data = file_get_contents($url);
          if($data === FALSE){
              $data = file_get_contents($file);
          }
          else{
              save($data);
          }
      }
      return $data;
  }

  return FALSE;
}
//-----------------------------------------------------------

//-----------------------------------------------------------
function save($data) {
     global $file;
    //если файл не существует, пробуем создать его
    if (!file_exists($file)) { if (FALSE === fopen($file, 'w')) { return FALSE; } }
    //сохраняем данные
    if (file_put_contents($file, $data) !== FALSE) { return TRUE; }
    return FALSE;
}
//-----------------------------------------------------------

//-----------------------------------------------------------
function set_variable($variable_name) {

 global $HTTP_GET_VARS;
 global $HTTP_POST_VARS;
 global $HTTP_COOKIE_VARS;
 global $_GET;
 global $_POST;
 global $_COOKIE;

 global $$variable_name;

 $$variable_name = "";

 if(isset($HTTP_GET_VARS[$variable_name])) $$variable_name = $HTTP_GET_VARS[$variable_name];
 if(isset($HTTP_POST_VARS[$variable_name])) $$variable_name = $HTTP_POST_VARS[$variable_name];
 if(isset($HTTP_COOKIE_VARS[$variable_name])) $$variable_name = $HTTP_COOKIE_VARS[$variable_name];

 if(isset($_GET[$variable_name])) $$variable_name = $_GET[$variable_name];
 if(isset($_POST[$variable_name])) $$variable_name = $_POST[$variable_name];
 if(isset($_COOKIE[$variable_name])) $$variable_name = $_COOKIE[$variable_name];

 if(is_string($$variable_name))
 {
   $$variable_name = str_replace("\0","", $$variable_name);
   $$variable_name = str_replace("\t"," ", $$variable_name);
   if (get_magic_quotes_gpc()) $$variable_name = stripslashes($$variable_name);
 }
}
//-----------------------------------------------------------
//##################################################################
?>
