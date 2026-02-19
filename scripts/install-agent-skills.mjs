#!/usr/bin/env node

import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const VALID_TARGETS = ["codex", "cursor", "claude", "antigravity"];
const TARGET_PATHS = {
  codex: [".codex", "skills"],
  cursor: [".cursor", "skills"],
  claude: [".claude", "skills"],
  antigravity: [".github", "skills"],
};

function parseArgs(argv) {
  const args = {
    targets: VALID_TARGETS,
    mode: "merge",
    dryRun: false,
  };

  for (const arg of argv) {
    if (arg === "--dry-run") {
      args.dryRun = true;
      continue;
    }
    if (arg.startsWith("--targets=")) {
      const targets = arg
        .slice("--targets=".length)
        .split(",")
        .map((target) => target.trim())
        .filter(Boolean);
      args.targets = [...new Set(targets)];
      continue;
    }
    if (arg.startsWith("--mode=")) {
      args.mode = arg.slice("--mode=".length);
      continue;
    }
    throw new Error(`Unknown argument: ${arg}`);
  }

  for (const target of args.targets) {
    if (!VALID_TARGETS.includes(target)) {
      throw new Error(
        `Invalid target: ${target}. Valid targets: ${VALID_TARGETS.join(", ")}`
      );
    }
  }

  if (!["replace", "merge"].includes(args.mode)) {
    throw new Error("mode must be 'replace' or 'merge'");
  }

  return args;
}

function listSkills(sourceRoot) {
  if (!fs.existsSync(sourceRoot)) {
    throw new Error(`Source not found: ${sourceRoot}`);
  }

  return fs
    .readdirSync(sourceRoot, { withFileTypes: true })
    .filter((entry) => entry.isDirectory())
    .map((entry) => entry.name)
    .filter((name) => fs.existsSync(path.join(sourceRoot, name, "SKILL.md")));
}

function copySkills({ sourceRoot, destinationRoot, skills, mode, dryRun }) {
  if (dryRun) {
    process.stdout.write(
      `[DRY-RUN] ${destinationRoot} <= ${skills.length} skill(s)\n`
    );
    return;
  }

  fs.mkdirSync(destinationRoot, { recursive: true });

  for (const skill of skills) {
    const sourceDir = path.join(sourceRoot, skill);
    const destinationDir = path.join(destinationRoot, skill);

    if (mode === "replace") {
      fs.rmSync(destinationDir, { recursive: true, force: true });
    }
    fs.cpSync(sourceDir, destinationDir, { recursive: true });
  }
}

function main() {
  const args = parseArgs(process.argv.slice(2));
  const scriptDir = path.dirname(fileURLToPath(import.meta.url));
  const repoRoot = path.resolve(scriptDir, "..");
  const sourceRoot = path.join(repoRoot, "tools", "agent-skills", "skills");
  const skills = listSkills(sourceRoot);

  if (skills.length === 0) {
    throw new Error(`No skills found in ${sourceRoot}`);
  }

  for (const target of args.targets) {
    const destinationRoot = path.join(repoRoot, ...TARGET_PATHS[target]);
    copySkills({
      sourceRoot,
      destinationRoot,
      skills,
      mode: args.mode,
      dryRun: args.dryRun,
    });
    process.stdout.write(
      `OK: ${target} -> ${path.relative(repoRoot, destinationRoot)} (${skills.length} skills)\n`
    );
  }
}

try {
  main();
} catch (error) {
  process.stderr.write(`Error: ${error.message}\n`);
  process.exit(1);
}
