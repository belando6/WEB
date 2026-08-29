<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// --- CPU ---
function cpu_usage(): float {
    $s1 = file_get_contents('/proc/stat');
    usleep(150000);
    $s2 = file_get_contents('/proc/stat');
    function parse_cpu_line($raw) {
        // First line starting with "cpu " (aggregate)
        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with(trim($line), 'cpu ')) {
                $parts = explode(' ', trim($line));
                // parts[0]='cpu', then user nice system idle iowait irq softirq steal
                $user=(int)$parts[1]+(int)$parts[2]+(int)$parts[3];
                $idle=(int)$parts[4]+(int)$parts[5];
                return [$user+$idle, $idle];
            }
        }
        return [0,0];
    }
    [$t1,$i1]=parse_cpu_line($s1);
    [$t2,$i2]=parse_cpu_line($s2);
    $dt=$t2-$t1; $di=$i2-$i1;
    if ($dt<=0) return 0.0;
    return round(100*($dt-$di)/$dt,1);
}

$core_count = substr_count(file_get_contents('/proc/cpuinfo'), 'processor');
$cpu_pct = cpu_usage();

// --- Memory (manual parse /proc/meminfo) ---
$mem_raw = file_get_contents('/proc/meminfo');
$mem_total_kb  = 0; $mem_avail_kb = 0; $swap_total_kb = 0; $swap_free_kb = 0;
foreach (explode("\n", $mem_raw) as $line) {
    if (str_starts_with($line,'MemTotal:'))       $mem_total_kb=(int)trim(substr($line,9));
    elseif (str_starts_with($line,'MemAvailable:'))$mem_avail_kb=(int)trim(substr($line,13));
    elseif (str_starts_with($line,'SwapTotal:'))   $swap_total_kb=(int)trim(substr($line,10));
    elseif (str_starts_with($line,'SwapFree:'))    $swap_free_kb=(int)trim(substr($line,9));
}
$mem_used_kb = max(0,$mem_total_kb-$mem_avail_kb);
$swap_used_kb=max(0,$swap_total_kb-$swap_free_kb);

// --- Disk (df without -P to allow --output) ---

// --- Disk (df -kP, skip header) ---
$disk=[];
$df_lines=explode("\n",trim((string)shell_exec("df -kP 2>/dev/null | awk 'NR>1{print $6,$3,$4,$5}'")));
foreach($df_lines as $ln){
    $p=explode(" ",$ln);
    if(count($p)<4)continue;
    [$mount,$used_kb,$avail_kb,$pct]=$p;
    if(str_starts_with($mount,"/proc")||str_starts_with($mount,"/sys")||str_starts_with($mount,"/run")||str_starts_with($mount,"/dev/shm"))continue;
    $size_kb=(int)$used_kb+(int)$avail_kb; if($size_kb<1048576)continue;
    $disk[]=["mount"=>$mount,"size_kb"=>$size_kb,"used_kb"=>(int)$used_kb,"avail_kb"=>(int)$avail_kb,"percent"=>(int)$pct];
}
// --- Uptime & Load ---
$up_raw=trim(file_get_contents('/proc/uptime'));
$uptime_sec=(float)explode(' ',$up_raw)[0];
$days=floor($uptime_sec/86400);
$hms=date('H:i',$uptime_sec%86400);

$loadavg=array_map('floatval',array_slice(explode(' ',trim(file_get_contents('/proc/loadavg'))),0,3));

// --- Processes ---
$procs=count(glob('/proc/[0-9]*'));

// --- Modules usage from nginx access.log (last 50k lines) ---
$modules=['almacen','chat','conversor','calorias','documentos','editor','ingles','transcribir'];
$log_file='/var/log/nginx/access.log';
$module_counts=array_fill_keys($modules,0);
$uri_counts=[];
$last_access='—';

if(file_exists($log_file)){
    $cmd="tail -n 50000 ".escapeshellarg($log_file)." | awk '{print \$7}'";
    $uris=array_filter(explode("\n",trim((string)shell_exec($cmd))));
    foreach($uris as $u){
        if(!$u)continue;
        $path=parse_url($u,PHP_URL_PATH)?:$u;
        $uri_counts[$path]=($uri_counts[$path]??0)+1;
        foreach($modules as $m){
            if(str_contains($path,'/'.$m.'/')||str_contains($path,'/'.$m.'.php')){$module_counts[$m]++;break;}
        }
    }
    // Last access timestamp
    $last_line=trim((string)shell_exec('tail -n 1 '.escapeshellarg($log_file)));
    if(preg_match('/\[(.+?)\]/',$last_line,$mm)){
        $ts=strtotime(str_replace('-','/',$mm[1]).' +0000');
        if($ts)$last_access=date('Y-m-d H:i:s',$ts);
    }
}

// --- DB sizes ---
$db_files=[
    '/var/www/html/WEB/chat/chat.db',
    '/var/www/html/WEB/conversor/conversor.db',
    '/var/www/html/WEB/calorias/alimentos.db',
    '/var/www/html/WEB/editor/library.db',
    '/var/www/html/WEB/ingles/data/english.db',
    '/var/www/html/WEB/ingles/data/ingles.db',
];
$dbs=[];
foreach($db_files as $f){
    if(file_exists($f))$dbs[]=['path'=>basename(dirname($f)).'/'.basename($f),'size_kb'=>(int)(filesize($f)/1024)];
}

// --- Log size ---
$log_size_kb=file_exists($log_file)?(int)(filesize($log_file)/1024):0;

// --- Top endpoints (max 8) ---
arsort($uri_counts,SORT_NUMERIC);
$top_endpoints=[];$i=0;
foreach($uri_counts as $u=>$c){if($i++>=8)break;$top_endpoints[]=['path'=>$u,'hits'=>$c];}

// --- Sort modules desc ---
$module_usage=[];arsort($module_counts);
foreach($module_counts as $m=>$c)$module_usage[]=['name'=>$m,'requests'=>$c];

echo json_encode([
    'generated_at'=>date('Y-m-d H:i:s'),
    'cpu'=>['percent'=>$cpu_pct,'cores'=>$core_count],
    'loadavg'=>$loadavg,
    'memory'=>['total_mb'=>round($mem_total_kb/1024),'used_mb'=>round($mem_used_kb/1024),'percent'=>round(100*$mem_used_kb/max(1,$mem_total_kb),1)],
    'swap'=>['total_mb'=>round($swap_total_kb/1024),'used_mb'=>round($swap_used_kb/1024),'percent'=>$swap_total_kb>0?round(100*$swap_used_kb/$swap_total_kb,1):0],
    'disk'=>$disk,
    'uptime'=>['days'=>$days,'time'=>$hms],
    'processes'=>$procs,
    'modules_usage'=>$module_usage,
    'top_endpoints'=>$top_endpoints,
    'dbs'=>$dbs,
    'log_size_kb'=>$log_size_kb,
    'last_access'=>$last_access,
],JSON_UNESCAPED_SLASHES);
