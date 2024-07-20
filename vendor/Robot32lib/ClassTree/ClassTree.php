<?php 
namespace Robot32lib\ClassTree;

class ClassTree {
    const JOKE =                "CCHUM";
    const WEATHER =             "CCWEA"; // query about weather
    const NEWS =                "CCNEW"; // current news
    const TECHNEWS =            "CCNEW.TEH"; // technology news
    const ELECTRONICS =         "CCELE"; // query relating to electronics or robotics
    const MICROCONTROLLERS =    "CCELE.MIC"; // microcontrollers
  const MICROPYTHON_ELECTRONICS="CCELE.MIC.PY"; // programming in Micropython
    const PROGRAMMING =         "CCPRO"; // computer programming
    const PYTHON =              "CCPRO.PY"; // Python
    const MICROPYTHON =         "CCPRO.PY.MIPY"; // Micropython
    const PHP =                 "CCPRO.PHP"; // PHP
    const JAVASCRIPT =          "CCPRO.JS"; // Javascript
    const CLIENT_JAVASCRIPT =   "CCPRO.JS.CLI"; // client (browser side) Javascript
    const SERVER_JAVASCRIPT =   "CCPRO.JS.SER"; // server side Javascript (for example Node)
    const JAVA =                "CCPRO.JA"; // Java
    const GOLANG =              "CCPRO.GO"; // Golang
    const DOTNET =              "CCPRO.NET"; // Microsoft C# or .NET
    const C_CPP =               "CCPRO.C"; // C or C++
    const C_MICROCONTROLLERS =  "CCPRO.C.MIC"; // C for microcontrollers
    const AI_PROGRAMMING =      "CCPRO.AI"; // AI related programming
    const AI =                  "CCAI"; // AI related 
  const AI_PROGRAMMING_GENERAL ="CCAI.AI.PRO"; // AI related programming
    const AI_GENERAL =          "CCAI.AI.GEN"; // Other AI related inquiries    
    const TECHNOLOGY =          "CCTEC"; // Technology
    const GPU =                 "CCTEC.GPU"; // Graphics cards
    const GENERAL =             "CCGEN"; // user made a general query that does not fit into other categories

    public function getTreeText(){
        return file_get_contents(__DIR__."/clastree.txt");
    }
}