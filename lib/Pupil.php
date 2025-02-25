<?php
namespace TeacherStory\Pupil;

use Exception;
use TeacherStory\Paths;

class Pupil {
    public function __construct(
        public int $number,
        public string $name,
        public int $baseHp,
        public int $baseProtection,
        public ?int $assignedSeat = null
    ) { }

    public static function getRandomName():string {
        $aNames = json_decode(file_get_contents(Paths::$dataFolder.'/pupil_names.json'),true)['data']??null;
        if ($aNames == null) throw new Exception("Couldn't find 'pupil_names.json'.");
        return $aNames[random_int(0,count($aNames)-1)];
    }
}
?>