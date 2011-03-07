<?

switch($manifest['version'])
{
  case 1:
    // all good
    break;
  default:
    query_file(dirname(__FILE__)."/db.sql");
}


?>