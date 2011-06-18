#!/usr/bin/env php
<?php

class Kompalator
{
	private $baseDir;
	private $file;

	public function __construct($file)
	{
		if (!file_exists($file)) {
			throw new Exception("File not found $file");
		}

		$this->file = $file;
		$this->baseDir = dirname($file) . '/';
	}

	public function process()
	{
		$this->debug('Start processing');

		if (($c = file_get_contents($this->file)) === false) {
			throw new Exception('Could not read: ' . $this->file);
		}

		$c = preg_replace_callback('@<script.*?src=["\'](.*?)["\'].*?/script>@is', array($this, 'replaceScript'), $c);

		$c = preg_replace_callback('@<link.*?href=["\'](.*?)["\'].*?>@is', array($this, 'replaceCss'), $c);
		
		$c = preg_replace_callback('@<img.*?src=["\'](.*?)["\'].*?>@is', array($this, 'replaceImg'), $c);

		$this->debug('Done.');
		return $c;
	}

	public function replaceImg($m)
	{
		$file = $this->baseDir . $m[1];

		$this->debug("replaceImg $file");

		if (file_exists($file)) {
			$this->debug('img replace: ' . $m[0]);

			return str_replace($m[1], $this->dataUrify($file), $m[0]);
		}

		$this->debug('img untouched: ' . $m[0]);

		return $m[0];
	}

	public function replaceCss($m)
	{
		$file = $this->baseDir . $m[1];

		$this->debug("replaceCss $file");

		if (file_exists($file)) {
			$this->debug('css replace: ' . $m[0]);

			return '<style>' . $this->minify($file, 'css') . "</style>";
		}

		$this->debug('css untouched: ' . $m[0]);

		return $m[0];
	}

	public function replaceScript($m)
	{
		$file = $this->baseDir . $m[1];
		
		$this->debug("replaceScript $file");

		if (file_exists($file)) {
			$this->debug('script replace: ' . $m[0]);

			return '<script>' . $this->minify($file, 'js') . "</script>";
		}
		
		$this->debug('script untouched: ' . $m[0]);

		return $m[0];
	}

	private function debug($msg)
	{
		fwrite(STDERR, "LOG: $msg\n");
	}

	private function minify($file, $type)
	{
		if (($c = file_get_contents($file)) === false) {
			throw new Exception("Could not read: $file");
		}

		$infile = tempnam(sys_get_temp_dir(), 'tmp');
		$outfile = tempnam(sys_get_temp_dir(), 'tmp');

		file_put_contents($infile, $c);

		$jarFile = $this->getYuiCompJar();

		$this->runCmd('cat ' . $infile . " | java -jar $jarFile --type " . $type . ' > ' . $outfile);

		$c = file_get_contents($outfile);

		unlink($infile);
		unlink($outfile);

		return $c;
	}

	private function getYuiCompJar()
	{
		foreach (glob($search = dirname($GLOBALS['argv'][0]) . '/yuicompressor-*.jar') as $file) {
			return $file;
		}

		throw new Exception("Could not find $search");
	}

	private function runCmd($cmd)
	{
		$this->debug("exec: $cmd");
		@system($cmd, $r);
		
		if ($r != 0) {
			throw new Exception('Command returned ' . $r);
		}
	}

	private function dataUrify($file)
	{
		if (!preg_match('@.(jpg|jpeg|png|gif)$@i', $file, $m)) {
			$this->debug("dataUrify: Could not get file extension of $file");
			return $file;
		}

		$mime = 'image/' . ($m[1] == 'jpg' ? 'jpeg' : $m[1]);

		if (($c = file_get_contents($file)) === false) {
			throw new Exception("Could not read: $file");
		}

		return 'data:' . $mime . ';base64,' . base64_encode($c);
	}

}

if (empty($argv[1])) {
	echo "missing arg\n";
	echo "syntax: {$argv[0]} file.html\n";
	exit(1);
}

try {
	$k = new Kompalator($argv[1]);
	echo $k->process();
	exit(0);
} catch (Exception $e) {
	fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
	exit(1);
}

