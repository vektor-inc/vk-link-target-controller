let gulp = require('gulp');
let jsValidate = require('gulp-jsvalidate');
let jsmin = require('gulp-jsmin');
let rename = require('gulp-rename');

gulp.task('test', () => {
    return gulp.src('js/*.js')
        .pipe(jsValidate())
})

gulp.task('jsmin', () => {
    return gulp.src('js/script.js')
        .pipe(jsmin())
        .pipe(rename({suffix: '.min'}))
        .pipe(gulp.dest('js/'));
})
