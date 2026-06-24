

   INFO  Seeding database.  


   Illuminate\Contracts\Container\BindingResolutionException 

  Target class [Database\Seeders\MaintenanceOrderSeedercat] does not exist.

  at vendor/laravel/framework/src/Illuminate/Container/Container.php:1124
    1120▕ 
    1121▕         try {
    1122▕             $reflector = new ReflectionClass($concrete);
    1123▕         } catch (ReflectionException $e) {
  ➜ 1124▕             throw new BindingResolutionException("Target class [$concrete] does not exist.", 0, $e);
    1125▕         }
    1126▕ 
    1127▕         // If the type is not instantiable, the developer is attempting to resolve
    1128▕         // an abstract type such as an Interface or Abstract Class and there is

      [2m+23 vendor frames [22m

  24  artisan:16
      Illuminate\Foundation\Application::handleCommand()

