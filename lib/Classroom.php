<?php
namespace TeacherStory\Classroom;

use LDLib\Database\LDPDO;
use LDLib\ErrorType;
use LDLib\OperationResult;
use LDLib\SuccessType;
use TeacherStory\Pupil\Pupil;

enum ClassroomActionTargetType {
    case DESK;
    case PUPIL;
    case TEACHER;
}

class Classroom {
    public static function generateNewClassicClassroom(LDPDO $pdo, int $playerId, int $classroomNumber):OperationResult {
        if ($pdo->query("SELECT * FROM classic_classrooms WHERE player_id=$playerId AND number=$classroomNumber")->fetch() !== false)
            return new OperationResult(ErrorType::USELESS, "There is already a classroom n°$classroomNumber for userId $playerId.");

        $sNow = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $pdo->query('START TRANSACTION');

        $stmt = $pdo->prepare('INSERT INTO classic_classrooms(player_id,number,layout,creation_date,last_action_date) VALUES (?,?,?,?,?)');
        $stmt->execute([$playerId,$classroomNumber,json_encode(self::generateNewClassicClassroomLayout()),$sNow,$sNow]);

        for ($i=1; $i<6; $i++) {
            $pupil = self::generateNewClassicPupil($i);
            $pupil->assignedSeat = $i;
            $stmt = $pdo->prepare('INSERT INTO classic_pupils(player_id,classroom_number,number,name,base_hp,base_protection,assigned_seat) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$playerId,$classroomNumber,$pupil->number,$pupil->name,$pupil->baseHp,$pupil->baseProtection,$pupil->assignedSeat]);
        };

        $pdo->query('COMMIT');

        return new OperationResult(SuccessType::SUCCESS);
    }

    public static function doTeacherAction(LDPDO $pdo, int $playerId, int $classroomNumber, string $actionId, array $targets) {
        return new OperationResult(ErrorType::INVALID,"Not implemented");
    }

    private static function generateNewClassicClassroomLayout() {
        $layout = [
            ['.','.','D','D','.','.','D','D','.','.'],
            ['.','.','D','D','.','.','D','D','.','.'],
            ['.','.','.','.','.','.','.','.','.','.'],
            ['.','D','D','.','.','.','.','D','D','.'],
            ['.','.','D','D','.','.','D','D','.','.'],
            ['.','.','D','D','.','.','D','D','.','.']
        ];

        return $layout;
    }

    private static function generateNewClassicPupil(int $pupilNumber):Pupil {
        return new Pupil($pupilNumber,Pupil::getRandomName(),random_int(3,8),random_int(2,5));
    }
}
?>