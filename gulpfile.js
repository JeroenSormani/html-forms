'use strict';

const gulp = require('gulp');
const sass = require('gulp-sass');
const uglify = require('gulp-uglify');
const rename = require("gulp-rename");
const cssmin = require('gulp-cssmin');
const browserify = require('browserify');
const streamify = require('gulp-streamify');
const sourcemaps = require('gulp-sourcemaps');
const wpPot = require('gulp-wp-pot');
const insert = require('gulp-insert');
const source = require('vinyl-source-stream');
const buffer = require('vinyl-buffer');
const gutil = require('gulp-util');

gulp.task('css', function () {
    let files = './assets/sass/[^_]*.scss';

    return gulp.src(files)
        // create .css file
        .pipe(sass())
        .pipe(rename({ extname: '.css' }))
        .pipe(gulp.dest('./assets/css'));
});

function js(file) {
	let filename = file.split('/').pop();
  	return browserify({ entries: [file] }).on('error', gutil.log)
		.transform("babelify", {
			 presets: [
			 	["@babel/preset-env", { "targets": "> 0.25%, not dead", "forceAllTransforms": true }]
			 ],
			 plugins: [
				["@babel/plugin-proposal-decorators", {"legacy": true }],
				["@babel/plugin-transform-react-jsx", {"pragma":"h" }]
			]
		 })
		.bundle()
		.pipe(source(filename))
		.pipe(buffer())
		.pipe(insert.wrap('(function () { var require = undefined; var module = undefined; var exports = undefined; var define = undefined;', '; })();'))
		.pipe(gulp.dest('./assets/js'));
}

gulp.task('js-public', () => js('./assets/browserify/public.js'));
gulp.task('js-admin', () => js('./assets/browserify/admin/admin.js'));
gulp.task('js', gulp.parallel('js-public', 'js-admin'));

gulp.task('minify-js', gulp.series('js', function() {
    return gulp.src(['./assets/js/**/*.js','!./assets/js/**/*.min.js'])
        .pipe(sourcemaps.init({loadMaps: true}))
        .pipe(streamify(uglify())).on('error', gutil.log)
        .pipe(rename({extname: '.min.js'}))
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest('./assets/js'));
}));

gulp.task('minify-css', gulp.series('css', function() {
    return gulp.src(["./assets/css/*.css", "!./assets/css/*.min.css"])
        .pipe(sourcemaps.init({loadMaps: true}))
        .pipe(cssmin({ sourceMap: true }))
        .pipe(rename({extname: '.min.css'}))
        .pipe(sourcemaps.write('./'))
        .pipe(gulp.dest("./assets/css"));
}));


gulp.task('pot', function () {
    const domain = 'html-forms';
    return gulp.src('src/**/**/*.php')
        .pipe(wpPot({ domain: domain}))
        .pipe(gulp.dest(`languages/${domain}.pot`));
});

gulp.task('default', gulp.series('minify-css', 'minify-js', 'pot'));

gulp.task('watch', function () {
    gulp.watch('./assets/sass/**/*.scss', gulp.series('css'));
    gulp.watch('./assets/browserify/**/*.js', gulp.series('js'));
});
