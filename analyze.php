#!/usr/bin/php

<?php

// Регулярное выражение для разбора строки лога
$pattern = '@(?P<ip>.*?) (?P<remote_log_name>.*?) (?P<userid>.*?) \[(?P<date>.*?):(?P<time>.*?)(?= ) (?P<timezone>.*?)\] \"(?P<request_method>.*?) (?P<path>.*?) (?P<request_version>HTTP/.{1,3})?\" (?P<response_code>\d{3}?) (?P<length>.*?) (?P<response_time>.*?) @';

$badRequests = []; // здесь будем хранить "плохие запросы"
$intervals = []; // здесь будем хранить интервалы
$responseTimeTreshold = 0;  // приемлемое время ответа, миллисекунды
$uptime = 100; //минимально допустимый уровень доступности, проценты
$count = 0; // счетчик считанных запросов

// Считываем аргументы командной строки
$options = getopt("u:t:");
$uptimeTreshold = $options['u'] / 100;
$responseTimeTreshold = $options['t'];

echo $responseTimeTreshold;

/*******************   Парсим лог-файл построчно функцией fgets. Для экономии памяти будем использовать генераторы          **********************/

// функция чтения
function readByLines($path) {
	$handle = fopen($path, "r");

	while (!feof($handle)) {
		yield trim(fgets($handle));
	}

	fclose($handle);
}

// Читаем стандартный поток построчно и сохраняем "плохие запросы"

foreach (readByLines('php://stdin', 'r') as $logLine) {
	if (preg_match($pattern, $logLine, $matches)) {

		$count++;

		$responseCode = $matches['response_code'];
		$responseTime = (int) $matches['response_time'];
		
		//	var_dump($matches);

		if (substr($responseCode,0,1) == '5' OR $responseTime > $responseTimeTreshold) {

				    // формируем массив, содержащий плохие запросы
				   	$badRequests[] = array(
				        	'count' => $count,
				        	'ip' => $matches['ip'],
				        	'date' => $matches['date'],
				        	'time' => $matches['time'],
				        	'response_code' => $responseCode,
				        	'response_time' => $responseTime,
				        	'request' => $logLine
				    );	   

				    echo 'count = '.$count.'
				    ';
		} 
	}
	else {
		echo 'cant parse the log line № '.$count + 1;
	}	
}
var_dump($badRequests);
/*echo $count.'
';



$badRequestsCount = count($badRequests);


echo 'bad requests count: '.$badRequestsCount.'
';

*/

/* Из плохих запросов составляем интервалы. Интервалы могут быть любой длины. Здесь также используем генератор	*/

// Функция-генератор, формирующая интервал

function getInterval($badRequests, $uptimeTreshold) {
	$badRequestsCount = count($badRequests);

	for ($i = 0; $i < $badRequestsCount; $i++) {
			for ($j = $i + 1; $j < $badRequestsCount; $j++) {
				// высчитываем uptime = количество плохих запросов / общее количество запросов 
				$uptime = round(1- ($j - $i + 1)/($badRequests[$j]['count'] - $badRequests[$i]['count'] + 1), 2);
				// Если uptime = 0 значит два или более запроса подряд были плохими (нулевой аптайм нам никакой полезной информации не дает), идем до следующего плохого запроса
				if ($uptime == 0) continue; 
				// Если доля отказов превышает границу, берем этот интервал
				if ($uptime < $uptimeTreshold) {
					yield $interval = array([
								'begin_time' => $badRequests[$i]['time'],
								'end_time' => $badRequests[$j]['time'],
								'total_request_count' => $badRequests[$j]['count'] - $badRequests[$i]['count'] + 1,
								'bad_requests_count' => $j - $i + 1,
								'uptime' => round(1- ($j - $i + 1)/($badRequests[$j]['count'] - $badRequests[$i]['count'] + 1), 2)

					]);
				}

			}
	}
}

// Выводим интервалы
$k=0;


foreach (getInterval($badRequests, $uptimeTreshold) as $key => $interval) {
	echo $k++.' '.$interval[0]['begin_time'].' '.$interval[0]['end_time'].' '.$interval[0]['uptime'];
	echo '
	';
}







// Функция для показа использованной памяти
function formatBytes($bytes, $precision = 2) {
    $units = array("b", "kb", "mb", "gb", "tb");

    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);

    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . " " . $units[$pow].'
    ';
}


print formatBytes(memory_get_peak_usage());

?>