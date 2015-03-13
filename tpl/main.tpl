<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<script src="/js/jquery-1.10.2.min.js"></script>

<script>
 function get_data(){
     var query = '/currency.php?cmd=get_data';
     get_json_data(query);
 }

 function get_json_data(query){
     $.getJSON(query, function(data) {
          var raw_data = data['data'];
          document.getElementById('data_div').innerHTML = raw_data;
                                     });
 }

 function set_currency(currency){
     var query = '/currency.php?cmd=set_currency&currency='+currency;
     get_json_data(query);
 }
</script>


</head>

<body onLoad="get_data();">

<div id="data_div"></div>

<hr>
<table width="300" cellspacing="0" cellpadding="0" border="0">
<tr><td width="200" align="left">Currency&nbsp;code</td><td align="left">Show</td></tr>
<!--!set_currency_filter-->
</table>
</body>
</html>