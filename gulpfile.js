// External packages.
const gulp = require('gulp')
const rename = require('gulp-rename')
const sass = require('gulp-sass')
const wpPot = require('gulp-wp-pot')
const babel = require('gulp-babel')
const uglify = require('gulp-uglify')
const uglifycss = require('gulp-uglifycss')
const zip = require('gulp-zip')

// Paths.
const paths = {
  styles: {
    src: 'assets/src/scss/',
    dist: 'assets/dist/css/',
    admin: {
      src: 'assets/src/scss/admin/',
      dist: 'assets/dist/css/admin/'
    }
  },
  scripts: {
    src: 'assets/src/js/',
    dist: 'assets/dist/js/',
    admin: {
      src: 'assets/src/js/admin/',
      dist: 'assets/dist/js/admin/'
    }
  },
  locale: 'languages/'
}

/*
 * Tasks.
 */

// Compile public styles.
const compileSCSS = () => {
  return gulp.src([
      paths.styles.src + 'main.scss'
    ])
    .pipe(sass())
    .pipe(rename({extname: '.css'}))
    .pipe(gulp.dest(paths.styles.dist))
}

// Minify public styles.
const minifyCSS = () => {
  return gulp.src([
      paths.styles.dist + 'main.css'
    ])
    .pipe(uglifycss({
      uglyComments: true
    }))
    .pipe(rename({suffix: '.min'}))
    .pipe(gulp.dest(paths.styles.dist))
}

// Compile public scripts.
const compileJS = () => {
  return gulp.src([
      paths.scripts.src + '**/*.js'
    ])
    .pipe(babel({
      presets: ['@babel/env']
    }))
    .pipe(gulp.dest(paths.scripts.dist))
}

// Minify public scripts.
const minifyJS = () => {
  return gulp.src([
      paths.scripts.dist + '**/*.js',
      '!' + paths.scripts.dist + '**/*.min.js',
    ])
    .pipe(uglify())
    .pipe(rename({suffix: '.min'}))
    .pipe(gulp.dest(paths.scripts.dist))
}

// Compile admin styles.
const compileAdminSCSS = () => {
  return gulp.src([
      paths.styles.admin.src + 'main.scss'
    ])
    .pipe(sass())
    .pipe(rename({extname: '.css'}))
    .pipe(gulp.dest(paths.styles.admin.dist))
}

// Minify admin styles.
const minifyAdminCSS = () => {
  return gulp.src([
      paths.styles.admin.dist + 'main.css'
    ])
    .pipe(uglifycss({
      uglyComments: true
    }))
    .pipe(rename({suffix: '.min'}))
    .pipe(gulp.dest(paths.styles.admin.dist))
}

// Compile admin scripts.
const compileAdminJS = () => {
  return gulp.src([
      paths.scripts.admin.src
    ])
    .pipe(babel({
      presets: ['@babel/env']
    }))
    .pipe(gulp.dest(paths.scripts.admin.dist))
}

// Minify admin scripts.
const minifyAdminJS = () => {
  return gulp.src([
      paths.scripts.admin.dist + '**/*.js',
      '!' + paths.scripts.admin.dist + '**/*.min.js',
    ])
    .pipe(uglify())
    .pipe(rename({suffix: '.min'}))
    .pipe(gulp.dest(paths.scripts.admin.dist))
}

// Generate localization file.
const generatePOT = () => {
  return gulp.src([
      '**/*.php',
      '!vendor/'
    ])
    .pipe(wpPot({
      domain: 'trackmage',
      package: 'TrackMage'
    }))
    .pipe(gulp.dest(paths.locale + 'trackmage.pot'))
}
gulp.task('generatePOT', generatePOT)

// Create WordPress plugin .zip file.
const createPluginFile = () => {
  return gulp.src([
    '**/*',
    '!node_modules/**',
    '!.git/**',
    '!assets/src/**',
    '!.gitignore',
    '!.gitmodules',
    '!gulpfile.js',
    '!package.json',
    '!package-lock.json',
    '!README.md',
    '!trackmage.zip'
  ])
  .pipe(zip('trackmage.zip'))
  .pipe(gulp.dest('.'))
}
gulp.task('createPluginFile', createPluginFile)

// Watch for changes.
const watchChanges = () => {
  gulp.watch([paths.styles.src + '**/*.scss', '!' + paths.styles.src.admin + '/**/*'], gulp.parallel(gulp.series(compileSCSS, minifyCSS), gulp.series(compileAdminSCSS, minifyAdminCSS)))
  gulp.watch(paths.styles.admin.src + '**/*.scss', gulp.series(compileAdminSCSS, minifyAdminCSS))
  gulp.watch([paths.scripts.src + '**/*.js', '!' + paths.scripts.src.admin + '/**/*'], gulp.series(compileJS, minifyJS))
  gulp.watch(paths.scripts.src.admin + '**/*.js', gulp.series(compileAdminJS, minifyAdminJS))
}
watchChanges.description = 'Watch for changes to all sources.'
gulp.task('watch', watchChanges)

// Build everything.
gulp.task('build',
  gulp.parallel(
    // generatePOT,
    gulp.series(compileSCSS, minifyCSS),
    gulp.series(compileAdminSCSS, minifyAdminCSS),
    gulp.series(compileJS, minifyJS),
    gulp.series(compileAdminJS, minifyAdminJS),
  )
)

// Build evenything and create a plugin file.
gulp.task('buildPlugin',
  gulp.series('build', 'createPluginFile')
)