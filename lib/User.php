<?php
namespace TeacherStory\User;

use Ds\Set;
use LDLib\{SuccessType,ErrorType,OperationResult};
use LDLib\Database\LDPDO;
use LDLib\User\User;

class RegisteredUser extends User {
    public function __construct(int $id, Set $titles, string $username, public readonly \DateTimeImmutable $registrationDate) {
        parent::__construct($id,$titles,$username);
    }

    public function isAdministrator():bool {
        return $this->roles->contains('Administrator');
    }

    public static function initFromRow(array $row) {
        $data = array_key_exists('data',$row) && array_key_exists('metadata',$row) ? $row['data'] : $row;
        return new self($data['id'],new Set(explode(',',$data['roles'])),$data['name'],new \DateTimeImmutable($data['registration_date']));
    }

    public static function validateUserInfos(LDPDO $pdo, ?string $username=null, ?string $password=null):OperationResult {
        if ($username !== null) {
            if (mb_strlen($username, "utf8") > 30) return new OperationResult(ErrorType::INVALID_DATA, 'The username must not have more than 30 characters.');
            else if (preg_match(RegisteredUser::$usernameRegex, $username) < 1) return new OperationResult(ErrorType::INVALID_DATA, 'The username contains invalid characters.');
            $stmt = $pdo->prepare('SELECT * FROM users WHERE name=? LIMIT 1'); $stmt->execute([$username]);
            if ($stmt->fetch() !== false) return new OperationResult(ErrorType::DUPLICATE, 'This username is already taken.');
        }

        if ($password !== null) {
            if (strlen($password) < 6) return new OperationResult(ErrorType::INVALID_DATA, 'The password length must be greater than 5 characters.');
            else if (strlen($password) > 150) return new OperationResult(ErrorType::INVALID_DATA, 'The password length must not be greater than 150 characters.');
        }

        return new OperationResult(SuccessType::SUCCESS);
    }
}
?>