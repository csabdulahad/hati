<?php

$hati = <<<HATI
                                  .'``'.__
                                 /      \ `'"-,
                .-''''--...__..-/ .     |      \
              .'               ; :'     '.  a   |
             /                 | :.       \     =\
            ;                   \':.      /  ,-.__;.-;`
           /|     .              '--._   /-.7`._..-;`
          ; |       '                |`-'      \  =|
          |/\        .   -' /     /  ;         |  =/
          (( ;.       ,_  .:|     | /     /\   | =|
           ) / `\     | `""`;     / |    | /   / =/
             | ::|    |      \    \ \    \ `--' =/
            /  '/\    /       )    |/     `-...-`
           /    | |  `\    /-'    /;
           \  ,,/ |    \   D    .'  \
            `""`   \  nnh  D_.-'L__nnh
                    `"""`
------------------------------------------------------------
|               Hati - A Speedy PHP Library                 |
|               RootData21 Inc.                             |
------------------------------------------------------------
HATI;

$msg = <<< MSG
Hati installation successfully. Consider doing:
➡️ Modify the hati.json configuration file
➡️ Move the .htaccess file to root from hati/config folder
➡️ Delete .htaccess if you don't need
MSG;

echo "
    <div style='width: 464px; background-color: whitesmoke; margin: auto; padding: 13px; box-shadow: 1px 1px 3px black; border-radius: 2px'>
        <pre>$hati</pre>
        <pre>$msg</pre>
    </div>
";
