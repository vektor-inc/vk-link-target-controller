const gulp = require('gulp');
const jsValidate = require('gulp-jsvalidate');
const jsmin = require('gulp-jsmin');
const rename = require('gulp-rename');
const replace = require("gulp-replace");

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

// replace_text_domain ////////////////////////////////////////////////
gulp.task("replace_text_domain", function(done) {
	// vk-admin
	gulp.src(["./inc/vk-admin/package/*"])
		.pipe(replace("vk_admin_textdomain","vk-link-target-controller"))
		.pipe(gulp.dest("./inc/vk-admin/package/"));
	done();
});
