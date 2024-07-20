<?php 
namespace Robot32lib\ClassTree;

class ClassTree {
    const JOKE =                "H0";
    const WEATHER =             "W0"; // query about weather
    const NEWS =                "N0"; // current news
    const TECHNEWS =            "NT"; // technology news
    const ELECTRONICS =         "E0"; // query relating to electronics or robotics
    const MICROCONTROLLERS =    "EM"; // microcontrollers
  const MICROPYTHON_ELECTRONICS="EMY"; // programming in Micropython
    const PROGRAMMING =         "P0"; // computer programming
    const PYTHON =              "PY"; // Python
    const MICROPYTHON =         "PYM"; // Micropython
    const PHP =                 "PP"; // PHP
    const JAVASCRIPT =          "PJ"; // Javascript
    const CLIENT_JAVASCRIPT =   "PJCL"; // client (browser side) Javascript
    const SERVER_JAVASCRIPT =   "PHSE"; // server side Javascript (for example Node)
    const JAVA =                "PJA"; // Java
    const GOLANG =              "PG"; // Golang
    const DOTNET =              "PN"; // Microsoft C# or .NET
    const C_CPP =               "PC"; // C or C++
    const C_MICROCONTROLLERS =  "PCM"; // C for microcontrollers
    const AI_PROGRAMMING =      "PA"; // AI related programming
    const AI =                  "A0"; // AI related 
  const AI_PROGRAMMING_GENERAL ="AP"; // AI related programming
    const TECHNOLOGY =          "T0"; // Technology
    const GPU =                 "TG"; // Graphics cards
    const GENERAL =             "G0"; // user made a general query that does not fit into other categories

    public function getTreeText(){
        $t = file_get_contents(__DIR__."/clastree.txt");
        $t = str_replace("\r\n","\n",$t); //windows
        $t = str_replace("\r","\n",$t); //mac
        $lines = explode("\n",$t);
        foreach($lines as $key=>$val){
            $lines[$key] = trim($val);
        }       
        return implode("\n",$lines);
    }
}