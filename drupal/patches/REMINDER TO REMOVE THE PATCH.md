When the bug is officially fixed in an upstream release of the module (e.g., `2.1.14`), here is the standard, foolproof process to update and remove the patch:

---

### 1. Built-in Safety Check: What Composer Will Do
Composer patches actually protect you here:
If you run `composer update drupal/workflow` while the patch is still defined, Composer will try to re-apply the patch to the new code. Because upstream already made the changes, the patch will fail to apply and Composer will warn you:
```
Could not apply patch! Skipping...
```
This ensures you won't accidentally apply conflicting changes or corrupt the codebase.

---

### 2. Steps to Remove the Patch When Updating

#### Step 1: Remove the patch definition from [drupal/composer.json](file:///Users/jryi/dev/konsolifin/drupal/composer.json)
Under `"extra": { "patches": ... }`, remove the `drupal/workflow` entry:

```diff
     "extra": {
         "patches": {
-            "drupal/workflow": {
-                "Fix TypeError in WorkflowConfigTransition::getWorkflowId during config import": "patches/workflow-transition-config-import-typeerror.patch"
-            }
         },
```
*(If you have no other patches, you can remove the `"patches"` block altogether).*

#### Step 2: Delete the patch file
```bash
rm drupal/patches/workflow-transition-config-import-typeerror.patch
```

#### Step 3: Update the Workflow module with Composer
```bash
cd drupal
composer update drupal/workflow --with-all-dependencies
```

#### Step 4: Run database updates and rebuild cache
```bash
drush updatedb -y
drush cache:rebuild
```

#### Step 5: Commit and push
```bash
git add composer.json composer.lock patches/
git commit -m "Update drupal/workflow and remove obsolete config import patch"
git push origin <branch>
```

---

> [!TIP]
> You can keep `cweagans/composer-patches` in `composer.json` even if you have no active patches. It won't affect performance or site behavior, and it will be ready whenever you need to patch another contributed module in the future.