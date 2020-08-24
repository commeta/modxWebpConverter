## Source bin files https://developers.google.com/speed/webp/docs/precompiled 
[downloads repository](https://storage.googleapis.com/downloads.webmproject.org/releases/webp/index.html)

## Download bin file in /connectors/converter/Binaries/ directory
and add\replace new file name in $suppliedBinaries Array in /connectors/converter/converter.php file, function getBinary(), example:
```PHP
	$suppliedBinaries = [
		'winnt' => 'cwebp-110-windows-x64.exe', // Microsoft Windows 64bit
		'darwin' => 'cwebp-110-mac-10_15', // MacOSX
		'sunos' => 'cwebp-060-solaris', // Solaris
		'freebsd' => 'cwebp-060-fbsd', //FreeBSD
		'linux' => [
			// Dynamically linked executable.
			// It seems it is slightly faster than the statically linked
			'cwebp-110-linux-x86-64',

			// Statically linked executable
			// It may be that it on some systems works, where the dynamically linked does not (see #196)
			'cwebp-103-linux-x86-64-static',

			// Old executable for systems in case both of the above fails
			'cwebp-061-linux-x86-64'
		]
	];
```
