/**
 * Copy the files of Driver.js, which are used for the intro, from node_modules to admin/driverjs/.
 *
 * Run via "npm run build:driver" (part of "npm run build").
 *
 * @source https://driverjs.com
 */
const fs = require( 'fs' );
const path = require( 'path' );

const root = path.resolve( __dirname, '..' );
const source = path.join( root, 'node_modules', 'driver.js' );
const target = path.join( root, 'admin', 'driverjs' );

// the files to copy: script, styles and the license of the library.
const files = {
	'dist/driver.js.iife.js': 'driver.js.iife.js',
	'dist/driver.css': 'driver.css',
	'license': 'LICENSE',
};

fs.mkdirSync( target, { recursive: true } );

for ( const [ from, to ] of Object.entries( files ) ) {
	const sourceFile = path.join( source, from );
	if ( ! fs.existsSync( sourceFile ) ) {
		console.error( 'Missing ' + sourceFile + ' - run "npm ci" first.' );
		process.exit( 1 );
	}
	fs.copyFileSync( sourceFile, path.join( target, to ) );
}

console.log( 'Driver.js copied to admin/driverjs/.' );
