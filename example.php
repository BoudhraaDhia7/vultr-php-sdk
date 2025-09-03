<?php
require __DIR__ . '/vendor/autoload.php';

use BoudhraaDhia7\VultrLaravelSymfony\VultrAPI;

$vultr = new VultrAPI('PUT_YOUR_API_KEY_HERE');

echo $vultr->listServers();//Data for all current account instances


$vultr->setSubId('31828385');//Must be set if interacting with a single instance actions
//$vultr->serverReboot(); //Reboots/restarts instance with id:31828385


//echo $vultr->responseAsString($vultr->serverDestroy());//Prints Success on HTTP 200 returned, else says Failed


$vultr->listRegions();//Returns regions
$vultr->listPlans();//Returns plans
$vultr->listISOs();//Returns ISO's
$vultr->listApps();//Returns Vultr one click apps
$vultr->listSnapshots();//Returns account snapshots
$vultr->listOS();//Returns vultr operating systems


//Creating a new instance
//First view:
$vultr->serverCreateOptions();
//For all options


//Create instance from snapshot:
$vultr->serverCreateDC('syd');//Sydney Australia location
$vultr->serverCreatePlan('vc2-2c-4gb');
$vultr->serverCreateType('SNAPSHOT', 'YQrb72d9-ded9-4f68-a5cg-7d4f96a534de');//Deploy with my snapshot id:YQrb72d9-ded9-4f68-a5cg-7d4f96a534de
$vultr->serverCreateLabel('Created with API x2');//label instance as "Created with API"
echo $vultr->serverCreate();//Creates instance/server with parameters set above (returns subid)


//DC, plan and type are required

$vultr->serverCreateDC('syd');//Sydney Australia location
$vultr->serverCreatePlan('vc2-2c-4gb');
$vultr->serverCreateType('ISO', 146817);//Deploy with my custom ISO id:146817
$vultr->serverCreateLabel('Created with API');//label instance as "Created with API"
echo $vultr->serverCreate();//Creates instance/server with parameters set above (returns subid)