<?php
namespace TeacherStory\Auth;

use LDLib\{ErrorType,TypedException,OperationResult,SuccessType};
use TeacherStory\User\RegisteredUser;
use TeacherStory\DataFetcher\DataFetcher;
use LDLib\Database\LDPDO;
use LDLib\User\User;
use TeacherStory\Context\HTTPContext;
use LDLib\Cache\LDRedis;
use LDLib\Database\MariaDBError;
use LDLib\Logger\Logger;
use LDLib\Security\Security;
use LDLib\Utils\Utils;

class Auth {
    private static string $inviteCodeRegex = '/^[\w0-9]+$/u';

    public static function registerUser(LDPDO $pdo, LDRedis $redis, HTTPContext $context, string $username, string $password):OperationResult {
        if (isset($context->request->header['sid'])) return new OperationResult(ErrorType::INVALID_CONTEXT, 'Can\'t register while a user is already authenticated.');

        $inviteSID = $context->request->cookie['invite_sid'] ?? null;
        if (!isset($inviteSID)) return new OperationResult(ErrorType::PROHIBITED, "You need an invite to register.");
        $inviteRow = self::fetchInviteSIDRow($pdo,$context->request->cookie['invite_sid']);
        if ($inviteRow == null) {
            $context->response->setCookie("invite_sid", "", time()-3600, "/", Utils::removeHostAddressPortPart($_SERVER['LD_LINK_DOMAIN']));
            Security::reportSusIP($context->getRealRemoteAddress(),10,"Invalid invite_sid used: $inviteSID",$pdo);
            return new OperationResult(ErrorType::INVALID_DATA, 'An invalid invite is being used.');
        } else if ($inviteRow['resulting_user_id'] !== null) {
            $context->response->setCookie("invite_sid", "", time()-3600, "/", Utils::removeHostAddressPortPart($_SERVER['LD_LINK_DOMAIN']));
            Security::reportSusIP($context->getRealRemoteAddress(),2,"Invalid invite_sid used: \"$inviteSID\" ; resulting_user_id is already set to {$inviteRow['resulting_user_id']}.",$pdo);
            return new OperationResult(ErrorType::INVALID_CONTEXT,'Session invalid, retry.');
        }

        $resValidation = RegisteredUser::validateUserInfos($pdo,$username,$password);
        if ($resValidation->resultType instanceof ErrorType) return $resValidation;

        $pdo->query('START TRANSACTION');

        $stmt = $pdo->prepare("INSERT INTO users (name,password,registration_date) VALUES (?,?,?) RETURNING *");
        $stmt->execute([$username,Auth::cryptPassword($password),(new \DateTime('now'))->format('Y-m-d H:i:s')]);
        if ($stmt->rowCount() != 1) { $pdo->query('ROLLBACK'); return new OperationResult(ErrorType::DATABASE_ERROR); }
        $userRow = $stmt->fetch();

        $stmt = $pdo->prepare('UPDATE invite_queues SET resulting_user_id=? WHERE id=?');
        $stmt->execute([$userRow['id'],$inviteRow['id']]);
        if ($stmt->rowCount() != 1) { $pdo->query('ROLLBACK'); return new OperationResult(ErrorType::DATABASE_ERROR); }

        $pdo->query('COMMIT');
        $context->response->setCookie("invite_sid", "", time()-3600, "/", Utils::removeHostAddressPortPart($_SERVER['LD_LINK_DOMAIN']));
        DataFetcher::storeUser($redis, $userRow, new \DateTime('now'));
        $user = RegisteredUser::initFromRow(DataFetcher::getUser($redis,$userRow['id']));
        return new OperationResult(SuccessType::SUCCESS, 'Successfully registered.', [$user->id], [$user]);
    }

    public static function loginUser(LDPDO $pdo, LDRedis $redis, HTTPContext $context, string $name, string $pwd, bool $rememberMe):OperationResult {
        $now = new \DateTime('now');
        $sNow = $now->format('Y-m-d H:i:s');
        $appId = $context->request->header['user-agent']??null;
        $remoteAddress = $context->server->getClientInfo($context->request->fd)['remote_ip'];

        $registerAttempt = function(?int $userId, bool $successful, ?string $errType) use($pdo,$appId,$sNow,$remoteAddress) {
            $stmt = $pdo->prepare("INSERT INTO sec_connection_attempts (user_id,app_id,remote_address,date,successful,error_type) VALUES (?,?,?,?,?,?)");
            $stmt->execute([$userId,$appId,$remoteAddress,$sNow,(int)$successful,$errType]);
        };

        if (isset($context->request->header['sid'])) return new OperationResult(ErrorType::INVALID_CONTEXT, 'A user is already authenticated.');

        if ($pdo->query("SELECT COUNT(*) FROM sec_connection_attempts WHERE DATE(date)=DATE('$sNow') AND successful=0")->fetch(\PDO::FETCH_NUM)[0] >= $_SERVER['LD_SEC_MAX_CONNECTION_ATTEMPTS'])
            return new OperationResult(ErrorType::PROHIBITED, 'Too many failed connection attempts for today.');

        // Check name+pwd
        if (preg_match(User::$usernameRegex,$name) == 0) return new OperationResult(ErrorType::INVALID_DATA, "The username contains invalid characters.");
        $stmt = $pdo->prepare("SELECT * FROM users WHERE name=? AND password=? LIMIT 1");
        $stmt->execute([$name,Auth::cryptPassword($pwd)]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($userRow == false) { $registerAttempt(null,false,ErrorType::NOT_FOUND->name); return new OperationResult(ErrorType::NOT_FOUND, 'User not found. Verify name and password.'); }

        // Check if banned
        $banRow = $pdo->query("SELECT * FROM sec_users_bans WHERE user_id={$userRow['id']} AND start_date<='$sNow' AND end_date>'$sNow' LIMIT 1")->fetch();
        if ($banRow !== false) return new OperationResult(ErrorType::PROHIBITED, 'User is banned until '.$banRow['end_date'].' (UTC+0).');

        // Generate session id and register connection
        $sid = bin2hex(random_bytes(16));
        $stmt = $pdo->prepare("INSERT INTO connections (user_id,session_id,app_id,created_at,last_activity_at) VALUES(?,?,?,?,?);");
        if ($stmt->execute([$userRow['id'],$sid,$appId,$sNow,$sNow]) === false) return new OperationResult(ErrorType::DATABASE_ERROR);

        $registerAttempt($userRow['id'],true,null);

        // All good, create cookie
        $time = $rememberMe ? time()+(60*60*24*120) : 0;
        $context->response->cookie('sid',$sid,$time,'/',$_SERVER['LD_LINK_DOMAIN'],true,true,'Lax','High');

        DataFetcher::storeUser($redis,$userRow,$now);
        $user = RegisteredUser::initFromRow(DataFetcher::getUser($redis,$userRow['id']));
        $context->authenticatedUser = $user;
        return new OperationResult(SuccessType::SUCCESS, 'User successfully logged in.', [$user->id], [$user]);
    }

    public static function logoutUser(LDPDO $pdo, HTTPContext $context):OperationResult {
        $context->deleteSidCookie();
        $stmt = $pdo->prepare("DELETE FROM connections WHERE session_id=?");
        $stmt->execute([$context->request->cookie['sid']]);
        return new OperationResult(SuccessType::SUCCESS, 'User successfully logged out.');
    }

    public static function changePassword(LDPDO $pdo, int $userId, string $oldPassword, string $newPassword):OperationResult {
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE id=? AND password=? LIMIT 1");
        $stmt->execute([$userId,Auth::cryptPassword($oldPassword)]);
        $userRow = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($userRow == false) return new OperationResult(ErrorType::INVALID_DATA, 'Old password is invalid or user not found.');

        $resValidation = RegisteredUser::validateUserInfos($pdo,null,$newPassword);
        if ($resValidation->resultType instanceof ErrorType) return $resValidation;

        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=? LIMIT 1");
        $stmt->execute([Auth::cryptPassword($newPassword),$userId]);
        return new OperationResult(SuccessType::SUCCESS, 'Password successfully changed.');
    }

    public static function logoutUserFromEverything(LDPDO $pdo, int $userId):OperationResult {
        $c = $pdo->query("DELETE FROM connections WHERE user_id=$userId")->rowCount();
        return new OperationResult(SuccessType::SUCCESS, "Terminated $c sessions.");
    }

    public static function cryptPassword(string $pwd, ?array &$fullString=null):string {
        $sCrypt = $_SERVER['LD_CRYPT_PASSWORD'];
        $res = preg_match('/^(.{28})(.{32})$/',crypt($pwd, $sCrypt),$m);
        $fullString = $pwd;
        if ($res === false || $res === 0) throw new TypedException("Password encryption failure.", ErrorType::INVALID_DATA);
        return $m[2];
    }

    public static function addInviteCode(LDPDO $pdo, int $referrerId, string $code, int $nUses):OperationResult {
        if (preg_match(self::$inviteCodeRegex,$code,$m) < 1) return new OperationResult(ErrorType::INVALID_DATA, 'Invite code contains invalid characters.');
        if (mb_strlen($code) < 8) return new OperationResult(ErrorType::INVALID_DATA, 'Code must be at least 8 characters long.');

        $stmt = $pdo->prepare('INSERT INTO invite_codes (referrer_id,code,max_referree_count) VALUES (?,?,?)');
        try { $stmt->execute([$referrerId,$code,$nUses]); } catch (\Throwable $t) {
            if (($stmt->errorInfo()[1]??'') === MariaDBError::ER_DUP_ENTRY->value) return new OperationResult(ErrorType::DUPLICATE, 'Duplicate code.');
            Logger::logThrowable($t);
            return new OperationResult(ErrorType::DATABASE_ERROR, 'Unknown database error.');
        }

        return new OperationResult(SuccessType::SUCCESS);
    }

    public static function processInviteCode(HTTPContext $context, LDPDO $pdo, string $code):OperationResult {
        if (preg_match(self::$inviteCodeRegex,$code,$m) < 1) return new OperationResult(ErrorType::INVALID_DATA, 'Invite code contains invalid characters.');
        if (mb_strlen($code) < 8) return new OperationResult(ErrorType::INVALID_DATA, 'Code must be at least 8 characters long.');

        $stmt = $pdo->prepare('SELECT * FROM invite_codes WHERE code=?');
        $stmt->execute([$code]);
        $invite = $stmt->fetch();
        if ($invite == null) return new OperationResult(ErrorType::NOT_FOUND, "There is no invite code '$code'.");

        if (isset($context->request->cookie['invite_sid'])) {
            $inviteRow = self::fetchInviteSIDRow($pdo,$context->request->cookie['invite_sid']);
            if ($inviteRow == null) $context->response->setCookie("invite_sid", "", time()-3600, "/", Utils::removeHostAddressPortPart($_SERVER['LD_LINK_DOMAIN']));
            else if (is_array($inviteRow)) return new OperationResult(ErrorType::USELESS, 'A valid invite code is already detected, go register!', [], [$invite]);
            else return new OperationResult(ErrorType::UNKNOWN);
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM invite_queues WHERE code=?');
        $stmt->execute([$code]);
        $referreeCount = $stmt->fetch()[0];
        if ($referreeCount >= $invite['max_referree_count']) return new OperationResult(ErrorType::LIMIT_REACHED, 'This invite code has expired. (Limit reached.)');

        // Try to add to queue
        $now = new \DateTime('now');
        $sid = bin2hex(random_bytes(16));
        $lockName = "teacherstory_invite_$code";
        if (!$pdo->getLock($lockName, 5)) return new OperationResult(ErrorType::DBLOCK_TAKEN);

        $stmt = $pdo->prepare("INSERT INTO invite_queues (code,date,session_id) VALUES (?,?,?)");
        $stmt->execute([$code,$now->format('Y-m-d H:i:s'),$sid]);
        $pdo->releaseLock($lockName);
        $context->response->setCookie("invite_sid", $sid, time()+(60*60*10), '/', Utils::removeHostAddressPortPart($_SERVER['LD_LINK_DOMAIN']));
        return new OperationResult(SuccessType::SUCCESS, null, [], [$invite]);
    }

    public static function fetchInviteSIDRow(LDPDO $pdo, string $sid):array {
        $stmt = $pdo->prepare('SELECT * FROM invite_queues WHERE session_id=? LIMIT 1');
        $stmt->execute([$sid]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row;
    }
}
?>