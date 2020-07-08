<?php
if(!file_get_contents('php://input')){
    header('Location: index.php');
    exit(0);
}

$config = require '../config.php';
$confirmation_token = $config['confirmation_token'];
$token = $config['token'];
$secret = $config['secret'];

$log = date('Y-m-d H:i:s') . ' new request: ' . file_get_contents('php://input');
file_put_contents(__DIR__ . '/log.txt', $log . PHP_EOL, FILE_APPEND);

$data = json_decode(file_get_contents('php://input'));
switch ($data->type) {
    //Если это уведомление для подтверждения адреса...
    case 'confirmation':
        //...отправляем строку для подтверждения
        echo $confirmation_token;
        return;
    
    //Если это уведомление о новом сообщении...
    case 'message_new':
        if($data->secret != $secret){
            echo('stupid hacker)). you are idiot');
            return;
        }
        $peer_id = $data->object->message->peer_id;
        if (isset($data->object->message->action)){
            if (isset($data->object->message->action->member_id)) $member = $data->object->message->action->member_id;
            switch($data->object->message->action->type){
                case 'chat_kick_user':
                    $request_params = array(
                            'chat_id' => $peer_id - 2000000000,
                            'member_id' => $member,
                            'access_token' => $token,
                            'v' => '5.120'
                        );
                    $query = file_get_contents('https://api.vk.com/method/messages.removeChatUser?'. http_build_query($request_params));
                    file_put_contents(__DIR__ . '/log.txt', date('Y-m-d H:i:s') . ' kick user: '. $query . PHP_EOL, FILE_APPEND);
                    break;
                case 'chat_invite_user':
                    if ($member > 0){
                        $user_name = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$member}&access_token={$token}&v=5.120"))->response[0]->first_name;
                        $request_params = array(
                                'message' => "Хай [id{$member}|{$user_name}].\nВ случае выхода из беседы ты будешь кикнут, чтобы вернуться пиши админу беседы.",
                                'peer_id' => $peer_id,
                                'access_token' => $token,
                                'v' => '5.120',
                                'random_id' => rand()
                            );
                        $query = file_get_contents('https://api.vk.com/method/messages.send?'. http_build_query($request_params));
                        file_put_contents(__DIR__ . '/log.txt', date('Y-m-d H:i:s') . ' add user: '. $query . PHP_EOL, FILE_APPEND);
                    }
                    break;
            }
            break;
        }
        $message = explode(" ", $data->object->message->text, 2);
        if (!isset($message[1])) $message[1] = null;
        $request_params = array(
            'peer_id' => $peer_id,
            'access_token' => $token,
            'v' => '5.120',
            'random_id' => rand()
        );
        
        switch($message[0]){
            case '!пока':
            case '!бан':
            case '!бб':
            case '!кик':
            case '!kick':
                if ($peer_id >= 2000000000){
                    $chatMembers = json_decode(file_get_contents("https://api.vk.com/method/messages.getConversationMembers?peer_id={$peer_id}&access_token={$token}&v=5.120"))->response->items;
                    $is_admin = false;
                    foreach($chatMembers as $item){
                        if ($item->member_id == $data->object->message->from_id && isset($item->is_admin) && $item->is_admin == true)
                            $is_admin = true;
                    }
                    if (!$is_admin)
                        $request_params['message'] = 'Вы не админ беседы, кикать нельзя';

                    elseif(isset($data->object->message->reply_message)){
                        $daun_id = $data->object->message->reply_message->from_id;
                        $kick_params = array(
                            'chat_id' => $peer_id - 2000000000,
                            'member_id' => $daun_id,
                            'access_token' => $token,
                            'v' => '5.120'
                        );
                        $query = file_get_contents('https://api.vk.com/method/messages.removeChatUser?'. http_build_query($kick_params));
                        file_put_contents(__DIR__ . '/log.txt', date('Y-m-d H:i:s') . ' kick user: '. $query . PHP_EOL, FILE_APPEND);
                        $json_query = json_decode($query);
                        if (isset($json_query->response)){
                            $user = $daun_id > 0 ? "[id{$daun_id}|{$daun_id}]" : "[club". abs($daun_id) . "|{$daun_id}]";
                            $request_params['message'] = "Участник {$user} был успешно кикнут\nПричина: {$message[1]}";
                        } elseif (isset($json_query->error)) {
                            $request_params['message'] = "Произошла ошибка {$json_query->error->error_code}\nТекст: {$json_query->error->error_msg}";
                        } else{
                            $request_params['message'] = "Произошла неизвестная ошибка, слава чтоты зделал[id239188570|🤪]";
                        }
                    } else {
                        $request_params['message'] = 'Чтобы кикнуть пользователя перешлите сообщение ответом и напишите !kick [причина]';
                    }
                } else {
                    $request_params['message'] = 'Эта функция работает только в беседах';
                }
                file_get_contents('https://api.vk.com/method/messages.send?'. http_build_query($request_params));
                break;
        }
        break;
}
echo('ok');
?>