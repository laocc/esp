### Opcache 相关函数：

opcache_compile_file(string $file)
- 无需运行，即可编译并缓存 PHP 脚本

opcache_get_configuration()
- 获取缓存的配置信息

opcache_get_status(boolean $get_scripts = TRUE)
- 获取缓存的状态信息

opcache_invalidate(string $script, boolean $force = FALSE)
- 废除脚本缓存

opcache_is_script_cached(string $file)
- 检查是否被缓存了

opcache_reset()
- 重置字节码缓存的内容


### opcache 相关设置

zend_extension = opcache.so
- 启用扩展，默认php.ini中没有此项，要手工添加

opcache.enable = 1
-  启动opCache

opcache.enable_cli = 0
-  针对支持CLI版本PHP启动opCache 一般被用来测试和调试

opcache.memory_consumption = 128
-  共享内存大小，单位为MB，默认128

opcache.interned_strings_buffer = 8
-  存储临时字符串缓存大小，单位为MB，PHP5.3.0以前会忽略此项配置，默认8

opcache.max_accelerated_files = 10000
-  缓存文件数最大限制，命中率不到100%，可以试着提高这个值
- 哈希表中可存储的脚本文件数量上限，200-100W之间，实际运行值为大于此设置值的后一个质数，默认10000
- 223, 463, 983, 1979, 3907, 7963, 16229, 32531, 65407, 130987

opcache.max_file_size = 0
- 以字节为单位的缓存的文件大小上限。设置为 0 表示缓存全部文件。

opcache.validate_timestamps = 1
- 是否启用心跳检测，默认1
- 如果启用，那么 OPcache 会每隔 revalidate_freq 设定的秒数 检查脚本是否更新。
- 如果禁用此选项，必须使用 opcache_reset() 或者 opcache_invalidate() 函数来手动重置 OPcache，
- 也可以 通过重启 Web 服务器来使文件系统更改生效。

opcache.revalidate_freq = 2
-  心跳检测频率，一定时间内检查文件的修改时间, 这里设置检查的时间周期, 默认为 2, 单位为秒- 
- 设置为 0 会导致针对每个请求,如果 validate_timestamps 配置指令设置为禁用，那么此设置项将会被忽略。

opcache.enable_file_override = 0
- 启用检查 PHP 脚本存在性和可读性的功能，无论文件是否已经被缓存，都会检查opCache缓存,可以提升性能。默认0
- 启用在file_exists()， is_file() 以及 is_readable() 这类函数时都会检查opCache缓存
- 但是如果禁用了 validate_timestamps 选项， 可能存在返回过时数据的风险。

opcache.fast_shutdown = 0
- 开启快速停止续发事件，依赖于Zend引擎的内存管理模块，一次释放全部请求变量的内存，而不是依次释放内存块，默认0
- 打开快速关闭, 打开这个在PHP Request Shutdown的时候回收内存的速度会提高
- 使用快速停止续发事件，也就是Zend是否一次性释放全部变量占用的内存，否则就是单个释放
- 从 PHP 7.2.0 开始，此配置指令被移除。 快速停止的续发事件的处理已经集成到 PHP 中， 只要有可能，PHP 会自动处理这些续发事件。


opcache.save_comments = 1
- 是否保存文件/函数的注释，默认1
- 禁用此配置指令可能会导致一些依赖注释或注解的 应用或框架无法正常工作， 比如： Doctrine， Zend Framework 2 以及 PHPUnit。

opcache.load_comments = 1
- 是否加载注释内容，和 save_comments 一起使用，以实现按需加载注释内容。
- 从 PHP 7.0.0 开始被移除

opcache.max_wasted_percentage = 5
- 浪费内存的上限，以百分比计，达到此上限，那么 OPcache 将产生重新启动续发事件，默认5

opcache.use_cwd = 1
- 防止同名哈希，若禁用可提高性能，默认1
- 如果启用，OPcache 将在哈希表的脚本键之后附加改脚本的工作目录， 以避免同名脚本冲突的问题。 禁用此选项可以提高性能，但是可能会导致应用崩溃。

opcache.revalidate_path = 0
- 是否禁止在同一个 include_path 已存在的缓存文件会被重用。 因此，若禁用将无法找到不在包含路径下的同名文件。默认0


opcache.optimization_level = 0x7FFFBFFF
- 控制优化级别的二进制位掩码，如0xffffffff
- 从 PHP 5.6.18 开始，默认值从 0xFFFFBFFF 修改为 0x7FFFBFFF

opcache.blacklist_filename = ''
- OPcache 黑名单文件列表位置
- 是一个列表文件，在这个文件里进行定义不缓存的文件，每行一个文件名，可用*和?通配符，分号为注释符号
- 将特定文件加入到黑名单：/var/www/broken.php
- 以字符 x 文件打头的文件：/var/www/x*
- 通配符匹配：/var/www/*-broken.php
- 列表被加载后，再修改该文件不是立即生效，直到php重启

opcache.consistency_checks = 0
- 如果是非 0 值，OPcache 将会每隔 N 次请求检查缓存校验和，生产环境只能设为0
- N 即为此配置指令的设置值。 由于此选项对于性能有较大影响，请尽在调试环境使用。

opcache.force_restart_timeout = 180
- 保持激活状态时间，秒，空闲超过此时间将重启。默认180
- 如果缓存处于非激活状态，等待多少秒之后计划重启。 如果超出了设定时间，则 OPcache 模块将杀除持有缓存锁的进程， 并进行重启。
- 如果选项 .log_verbosity_level>=2，当发生重启时将在日志中记录一条错误信息。

opcache.log_verbosity_level = 1
- OPcache 模块的日志级别。 默认情况下，仅有致命级别（0）及错误级别（1）的日志会被记录。 其他可用的级别有：警告（2），信息（3）和调试（4）。默认1

opcache.error_log = ''
- 错误日志文件，留空视为stderr,错误日志将被送往标准错误输出 （通常情况下是 Web 服务器的错误日志文件）。

opcache.preferred_memory_model = ''
- 首选的内存模块，建议不要设此项，系统会选择适用的模块，如果留空，OPcache 会选择适用的模块， 通常情况下，自动选择就可以满足需求。
- 可选值包括： mmap，shm, posix 以及 win32。

opcache.restrict_api = ''
- 仅允许路径是以指定字符串开始的 PHP 脚本调用 OPcache API 函数。 默认""，表示不做限制。

opcache.protect_memory = 0
- 保护共享内存，以避免执行脚本时发生非预期的写入。 仅用于内部调试。默认0

opcache.mmap_base = ''
- 在 Windows 平台上共享内存段的基地址。 所有的 PHP 进程都将共享内存映射到同样的地址空间。 使用此配置指令避免"无法重新附加到基地址"的错误。


opcache.inherited_hack = 1
- 在 PHP 5.3 之前的版本，OPcache 会存储代码中使用 DECLARE_CLASS opCache 来实现继承的位置。
- 当文件被加载之后，OPcache 会尝试使用当前环境来绑定被继承的类。 由于当前脚本中可能并不需要 DECLARE_CLASS opCache，如果这样的脚本需要对应的opCache被定义时， 可能无法运行。
- 在 PHP 5.3 及后续版本中，此配置指令会被忽略。默认1

opcache.dups_fix = 0
- 可以暂时性的解决”can’t redeclare class”错误.
- 仅作为针对 “不可重定义类”错误的一种解决方案。默认0


opcache.file_cache = ""
- 配置二级缓存目录并启用二级缓存。
- 启用二级缓存可以在 SHM 内存满了、服务器重启或者重置 SHM 的时候提高性能。
- 默认值为空字符串 ""，表示禁用基于文件的缓存。

opcache.file_cache_only = 0
- 启用或禁用共享内存中的opcode缓存。，默认0

opcache.file_cache_consistency_checks = 1
- 当从文件缓存中加载脚本的时候，是否对文件的校验和进行验证。默认1

opcache.file_cache_fallback = 1
- 在 Windows 平台上，当一个进程无法附加到共享内存的时候， 使用基于文件的缓存，也即：file_cache_only=1。 需要显示的启用文件缓存。

opcache.huge_code_pages = 0
- 启用或者禁用将 PHP 代码（文本段）拷贝到 HUGE PAGES 中。 此项配置指令可以提高性能，但是需要在 OS 层面进行对应的配置。默认0

opcache.validate_permission = 0
- 针对当前用户，验证缓存文件的访问权限。默认0

opcache.validate_root = 0
- 在 chroot 的环境中避免命名冲突。 为了防止进程访问到 chroot 环境之外的文件，应该在 chroot 的情况下启用这个选项。默认0

opcache.file_update_protection = 2
- 如果文件的最后修改时间距现在不足此项配置指令所设定的秒数，那么这个文件不会进入到缓存中。 默认2
- 这是为了防止尚未完全修改完毕的文件进入到缓存。 如果你的应用中不存在部分修改文件的情况，把此项设置为 0 可以提高性能。

opcache.lockfile_path = '/tmp'
- 用来存储共享锁文件的绝对路径（仅适用于 *nix 操作系统）。默认/tmp

opcache.opt_debug_level = 0
- 出于对不同阶段的优化情况进行调试的目的，生成opCache转储。
- 设置为 0x10000 会在进行优化之前输出编译器编译后的opCache， 设置为 0x20000 会输出优化后的opCache。默认0
