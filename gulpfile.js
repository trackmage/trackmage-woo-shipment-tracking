// External packages.
const gulp = require("gulp"),
  rename = require("gulp-rename"),
  sass = require("gulp-sass"),
  wpPot = require("gulp-wp-pot"),
  babel = require("gulp-babel"),
  uglify = require("gulp-uglify"),
  uglifycss = require("gulp-uglifycss"),
  zip = require("gulp-zip");

// Paths.
const paths = {
  styles: {
    frontend: {
      src: "assets/src/scss/frontend/",
      dist: "assets/dist/css/frontend/"
    },
    admin: {
      src: "assets/src/scss/admin/",
      dist: "assets/dist/css/admin/"
    }
  },
  scripts: {
    frontend: {
      src: "assets/src/js/frontend/",
      dist: "assets/dist/js/frontend/"
    },
    admin: {
      src: "assets/src/js/admin/",
      dist: "assets/dist/js/admin/"
    }
  },
  locale: "languages/"
};

// Task: compile public styles.
const compileScss = () => {
  return gulp
    .src([paths.styles.frontend.src + "main.scss"])
    .pipe(sass())
    .pipe(rename({ extname: ".css" }))
    .pipe(gulp.dest(paths.styles.frontend.dist));
};

// Task: minify public styles.
const minifyCss = () => {
  return gulp
    .src([paths.styles.frontend.dist + "main.css"])
    .pipe(
      uglifycss({
        uglyComments: true
      })
    )
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.styles.frontend.dist));
};

// Task: compile public scripts.
const compileJs = () => {
  return gulp
    .src([paths.scripts.frontend.src + "**/*.js"])
    .pipe(
      babel({
        presets: ["@babel/env"]
      })
    )
    .pipe(gulp.dest(paths.scripts.frontend.dist));
};

// Task: minify public scripts.
const minifyJs = () => {
  return gulp
    .src([
      paths.scripts.frontend.dist + "**/*.js",
      "!" + paths.scripts.frontend.dist + "**/*.min.js"
    ])
    .pipe(uglify())
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.scripts.frontend.dist));
};

// Task: compile admin styles.
const compileAdminScss = () => {
  return gulp
    .src([paths.styles.admin.src + "main.scss"])
    .pipe(sass())
    .pipe(rename({ extname: ".css" }))
    .pipe(gulp.dest(paths.styles.admin.dist));
};

// Task: minify admin styles.
const minifyAdminCss = () => {
  return gulp
    .src([paths.styles.admin.dist + "main.css"])
    .pipe(
      uglifycss({
        uglyComments: true
      })
    )
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.styles.admin.dist));
};

// Task: compile admin scripts.
const compileAdminJs = () => {
  return gulp
    .src([paths.scripts.admin.src + "**/*.js"])
    .pipe(
      babel({
        presets: ["@babel/env"]
      })
    )
    .pipe(gulp.dest(paths.scripts.admin.dist));
};

// Task: minify admin scripts.
const minifyAdminJs = () => {
  return gulp
    .src([
      paths.scripts.admin.dist + "**/*.js",
      "!" + paths.scripts.admin.dist + "**/*.min.js"
    ])
    .pipe(uglify())
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.scripts.admin.dist));
};

// Generate localization file.
const generatePot = () => {
  return gulp
    .src(["**/*.php", "!vendor/", "!tests/", "!docker/"])
    .pipe(
      wpPot({
        domain: "trackmage",
        package: "TrackMage"
      })
    )
    .pipe(gulp.dest(paths.locale + "trackmage.pot"));
};
gulp.task("generatePot", generatePot);

// Watch for changes.
const watchChanges = () => {
  gulp.watch(
    [paths.styles.frontend.src + "**/*.scss"],
    gulp.series(compileScss, minifyCss)
  );
  gulp.watch(
    paths.styles.admin.src + "**/*.scss",
    gulp.series(compileAdminScss, minifyAdminCss)
  );
  gulp.watch(
    [paths.scripts.frontend.src + "**/*.js"],
    gulp.series(compileJs, minifyJs)
  );
  gulp.watch(
    paths.scripts.admin.src + "**/*.js",
    gulp.series(compileAdminJs, minifyAdminJs)
  );
};
watchChanges.description = "Watch for changes to all sources.";
gulp.task("watch", watchChanges);

// Build everything.
gulp.task(
  "build",
  gulp.parallel(
    // generatePot,
    gulp.series(compileScss, minifyCss),
    gulp.series(compileAdminScss, minifyAdminCss),
    gulp.series(compileJs, minifyJs),
    gulp.series(compileAdminJs, minifyAdminJs)
  )
);
