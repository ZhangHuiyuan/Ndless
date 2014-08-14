<?php
if($argc != 3)
	die("Usage: " . $argv[0] . " /path/to/idc/files /path/to/output/file\n");

//Has to be in the correct order as it's a multidimensional array! (OS-specific)
$idc_files = array("OS_ncas-3.6.0.idc", "OS_cas-3.6.0.idc", "OS_ncascx-3.6.0.idc", "OS_cascx-3.6.0.idc");

$syscall_nr_list = fopen(__DIR__ . "/../../../../ndless-sdk/include/syscall-list.h", "r");
if($syscall_nr_list === FALSE)
	die("Couldn't open syscall-list.h!\n");

$lines = array();
while(($lines[] = fgets($syscall_nr_list)) !== FALSE);
fclose($syscall_nr_list);

$i = 0;
while(true)
{
	if(strpos($lines[$i++], "START_OF_LIST") !== FALSE)
		break;
}

//$syscalls[0] = "fopen" etc.
$syscalls = array();
$counter = 0;

while(true)
{
	if(strpos($lines[$i], "END_OF_LIST") !== FALSE)
		break;
	
	
	$matches = array();
	//Explanation in mkStubs.php
	$found = preg_match("%#define e_(.+) (\\d+)( .*)?%", $lines[$i], $matches);
	$i++;
	
	if($found === 0)
		continue;
		
	if($matches[2] != $counter)
		die("Error: Syscall numbers not contiguous (Expected " . $counter . " instead of " . $matches[2] . ")!");
		
	++$counter;
		
	if(isset($syscalls[$matches[2]]))
		echo "Warning: Found syscall " . $matches[2] . " more than once!\n";
	else
		$syscalls[$matches[2]] = $matches[1];
}

echo "Found " . $counter . " syscalls!\n";

$syscall_addr_list = fopen($argv[2], "w");
if($syscall_addr_list === FALSE)
	die("Couldn't open list for syscall addresses!\n");
	
$syscall_addrs = array();
foreach($idc_files as $nr => $idc_file)
{
	$filename = $argv[1] . "/" . $idc_file;
	$idc_fp = fopen($filename, "r");
	if($idc_fp === FALSE)
		die("Couldn't open '" . $idc_fp . "'!\n");
	
	while(($line = fgets($idc_fp)) !== FALSE)
	{
		$matches = array();
		$found = preg_match("%\s*MakeName\s*\\((.+),\s+\"(.+)\"\);%", $line, $matches);
		
		if($found === 0)
			continue;
			
		$syscall_addrs[$nr][$matches[2]] = $matches[1];
	}
	
	fclose($idc_fp);
}

$count_os = count($idc_files);
$count_syscalls = count($syscalls);

$header = <<<EOF
#ifndef SYSCALL_ADDR_LIST_H
#define SYSCALL_ADDR_LIST_H

//This file has been autogenerated by mkSyscalls.php

#ifdef __cplusplus
constexpr
#endif // __cplusplus
unsigned int syscall_addrs[${count_os}][${count_syscalls}] =
{

EOF;

fwrite($syscall_addr_list, $header);

for($nr = 0; $nr < $count_os; $nr++)
{
	fwrite($syscall_addr_list, "{\n");
	for($syscall = 0; $syscall < $count_syscalls; $syscall++)
	{
		$syscall_name = $syscalls[$syscall];
		if(!isset($syscall_addrs[$nr][$syscall_name]))
		{
			echo "Warning: Syscall '" . $syscall_name . "' not found in '" . $idc_files[$nr] . "'!\n";
			fwrite($syscall_addr_list, "0x0,\n");
		}
		else
			fwrite($syscall_addr_list, $syscall_addrs[$nr][$syscall_name] . ",\n");
	}
	fwrite($syscall_addr_list, "},\n");
}

$footer = <<<EOF
};

#endif // !SYSCALL_ADDR_LIST_H
EOF;

fwrite($syscall_addr_list, $footer);

fclose($syscall_addr_list);
?>
