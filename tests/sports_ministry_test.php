<?php
require_once dirname(__DIR__).'/core/sports_service.php';
$failures=[];$passes=0;
function check(bool $ok,string $name):void{global $failures,$passes;if($ok){$passes++;echo "PASS: $name\n";}else{$failures[]=$name;echo "FAIL: $name\n";}}
$tz=new DateTimeZone('Africa/Nairobi');
check(sports_age('2008-08-16',new DateTimeImmutable('2026-08-15',$tz))===17,'birthday boundary before birthday');
check(sports_age('2008-08-16',new DateTimeImmutable('2026-08-16',$tz))===18,'birthday boundary on birthday');
check(sports_age('2008-02-29',new DateTimeImmutable('2025-02-28',$tz))===16,'leap-day age before March 1');
check(sports_age('2008-02-29',new DateTimeImmutable('2025-03-01',$tz))===17,'leap-day age increments March 1');
try{sports_age('2030-01-01',new DateTimeImmutable('2026-01-01',$tz));check(false,'future DOB rejected');}catch(InvalidArgumentException $e){check(true,'future DOB rejected');}
check(sports_normalize_phone('0712 345 678')==='+254712345678','Kenyan phone normalization');
$base=['sports_date_of_birth'=>'2000-01-01','whatsapp_phone'=>'0712345678','general_estate'=>'Umoja','education_level'=>'graduate','primary_position'=>'striker','preferred_jersey_number'=>'9','sports_rules_accepted'=>'1','publication_acknowledged'=>'1'];
check(sports_validate_application($base,new DateTimeImmutable('2026-08-16',$tz))['errors']===[],'valid adult application');
$bad=$base;$bad['education_level']='invalid';check(sports_validate_application($bad,new DateTimeImmutable('2026-08-16',$tz))['errors']!==[],'invalid education rejected');
$bad=$base;$bad['primary_position']='invalid_position';check(sports_validate_application($bad,new DateTimeImmutable('2026-08-16',$tz))['errors']!==[],'non-allowlisted position rejected');
$minor=$base;$minor['sports_date_of_birth']='2010-01-01';check(sports_validate_application($minor,new DateTimeImmutable('2026-08-16',$tz))['errors']!==[],'minor guardian data required');
check(!sports_public_text_is_safe('Call 0712345678 for payment'),'public phone rejected');
check(!sports_public_text_is_safe('Email help@example.org'),'public email rejected');
check(sports_public_text_is_safe('A disciplined player committed to weekly training.'),'safe public biography accepted');
check(sports_application_restricts_portal(['application_source'=>'new_user','status'=>'pending']),'new-user pending application restricts portal');
check(!sports_application_restricts_portal(['application_source'=>'existing_user','status'=>'pending']),'existing-user pending application preserves portal');
check(!sports_application_restricts_portal(['application_source'=>'existing_user','status'=>'rejected']),'existing-user rejection preserves portal');
check(!sports_application_restricts_portal(['application_source'=>'existing_user','status'=>'withdrawn']),'existing-user withdrawal preserves portal');
echo "\n$passes passed, ".count($failures)." failed.\n";exit($failures?1:0);
