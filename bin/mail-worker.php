<?php
if(PHP_SAPI!=='cli') exit;
require dirname(__DIR__).'/app/bootstrap.php';
for($i=0;$i<50;$i++) {
    $mail=transaction(function(){ $m=query("SELECT * FROM email_outbox WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE")->fetch(); if($m) query("UPDATE email_outbox SET status='sending',attempts=attempts+1 WHERE id=?",[$m['id']]); return $m; });
    if(!$mail) break;
    if(cfg('mail_transport')==='log') { file_put_contents(ROOT.'/storage/logs/mail.log',json_encode($mail).PHP_EOL,FILE_APPEND|LOCK_EX); $ok=true; }
    else { $from=cfg('mail_from'); if(!filter_var($from,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$from.$mail['recipient'].$mail['subject'])) throw new RuntimeException('Invalid mail headers'); $ok=mail($mail['recipient'],$mail['subject'],$mail['body'],['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']); }
    query('UPDATE email_outbox SET status=? WHERE id=?',[$ok?'sent':'failed',$mail['id']]);
}
echo "Queue processed. Log transport writes locally; it does not deliver email.\n";
