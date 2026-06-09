<?php

namespace Kailyn\Template;

class Compiler
{
    protected array $directives = [];

    public function compile(string $template): string
    {
        $template = $this->compileComments($template);
        $template = $this->compileRawEchos($template);
        $template = $this->compileEscapedEchos($template);
        $template = $this->compileDirectives($template);
        $template = $this->compilePhpBlocks($template);

        return $template;
    }

    protected function compileComments(string $template): string
    {
        return preg_replace('/\{\{--(.*?)--\}\}/s', '<?php /* $1 */ ?>', $template);
    }

    protected function compileRawEchos(string $template): string
    {
        return preg_replace('/\{!!\s*(.+?)\s*!!\}/s', '<?php echo $1; ?>', $template);
    }

    protected function compileEscapedEchos(string $template): string
    {
        return preg_replace('/\{\{\s*(.+?)\s*\}\}/s', '<?php echo htmlspecialchars($1, ENT_QUOTES, "UTF-8"); ?>', $template);
    }

    protected function compileDirectives(string $template): string
    {
        $regexDirectives = [
            // Loops (before conditionals to avoid partial matches)
            '/@foreach\s*\((.*)\)/' => '<?php foreach ($1): ?>',
            '/@endforeach/' => '<?php endforeach; ?>',
            '/@for\s*\((.*)\)/' => '<?php for ($1): ?>',
            '/@endfor/' => '<?php endfor; ?>',
            '/@while\s*\((.*)\)/' => '<?php while ($1): ?>',
            '/@endwhile/' => '<?php endwhile; ?>',
            '/@continue(?:\s*\((.*)\))?/' => '<?php if (!(isset($1) && !($1))): continue; endif; ?>',
            '/@break(?:\s*\((.*)\))?/' => '<?php if (!(isset($1) && !($1))): break; endif; ?>',

            // Conditionals
            '/@unless\s*\((.*)\)/' => '<?php if (!($1)): ?>',
            '/@endunless/' => '<?php endif; ?>',
            '/@isset\s*\((.*)\)/' => '<?php if (isset($1)): ?>',
            '/@endisset/' => '<?php endif; ?>',
            '/@empty\s*\((.*)\)/' => '<?php if (empty($1)): ?>',
            '/@endempty/' => '<?php endif; ?>',
            '/@if\s*\((.*)\)/' => '<?php if ($1): ?>',
            '/@elseif\s*\((.*)\)/' => '<?php elseif ($1): ?>',
            '/@else/' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',

            // Layout
            '/@extends\s*\(\s*\'([^\']+)\'\s*\)/' => '<?php $__env->startLayout(\'$1\'); ?>',
            '/@extends\s*\(\s*"([^"]+)"\s*\)/' => '<?php $__env->startLayout(\'$1\'); ?>',
            '/@section\s*\(\s*\'([^\']+)\'\s*,\s*(.+)\s*\)/' => '<?php $__env->setSection(\'$1\', $2); ?>',
            '/@section\s*\(\s*"([^"]+)"\s*,\s*(.+)\s*\)/' => '<?php $__env->setSection(\'$1\', $2); ?>',
            '/@section\s*\(\s*\'([^\']+)\'\s*\)/' => '<?php $__env->startSection(\'$1\'); ?>',
            '/@section\s*\(\s*"([^"]+)"\s*\)/' => '<?php $__env->startSection(\'$1\'); ?>',
            '/@endsection/' => '<?php $__env->stopSection(); ?>',
            '/@stop/' => '<?php $__env->stopSection(); ?>',
            '/@show/' => '<?php $__env->showSection(); ?>',
            '/@parent/' => '<?php echo $__env->yieldContent(\'@parent\'); ?>',

            // Include
            '/@includeIf\s*\(\s*\'([^\']+)\'\s*(?:,\s*(.+))?\)/' => '<?php echo $__env->includeIf(\'$1\', $2 ?? []); ?>',
            '/@includeWhen\s*\(\s*(.+?)\s*,\s*\'([^\']+)\'\s*(?:,\s*(.+))?\)/' => '<?php if ($1): ?><?php echo $__env->include(\'$2\', $3 ?? []); ?><?php endif; ?>',

            // JSON
            '/@json\s*\((.*)\)/' => '<?php echo json_encode($1, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>',

            // Debug
            '/@dd\s*\((.*)\)/' => '<?php dd($1); ?>',
            '/@dump\s*\((.*)\)/' => '<?php var_dump($1); ?>',
        ];

        foreach ($regexDirectives as $pattern => $replacement) {
            $template = preg_replace($pattern, $replacement, $template);
        }

        $pregCallbacks = [
            '/@yield\s*\(\s*\'([^\']+)\'\s*(?:,\s*(.+))?\)/' => function ($m) {
                $name = $m[1];
                $default = $m[2] ?? null;
                if ($default !== null) {
                    return "<?php echo \$__env->yieldContent('{$name}', {$default}); ?>";
                }
                return "<?php echo \$__env->yieldContent('{$name}'); ?>";
            },
            '/@yield\s*\(\s*"([^"]+)"\s*(?:,\s*(.+))?\)/' => function ($m) {
                $name = $m[1];
                $default = $m[2] ?? null;
                if ($default !== null) {
                    return "<?php echo \$__env->yieldContent('{$name}', {$default}); ?>";
                }
                return "<?php echo \$__env->yieldContent('{$name}'); ?>";
            },
            '/@include\s*\(\s*\'([^\']+)\'\s*(?:,\s*(.+))?\)/' => function ($m) {
                $view = $m[1];
                $data = $m[2] ?? '[]';
                return "<?php echo \$__env->include('{$view}', {$data}); ?>";
            },
            '/@include\s*\(\s*"([^"]+)"\s*(?:,\s*(.+))?\)/' => function ($m) {
                $view = $m[1];
                $data = $m[2] ?? '[]';
                return "<?php echo \$__env->include('{$view}', {$data}); ?>";
            },
            '/@component\s*\(\s*\'([^\']+)\'\s*(?:,\s*(\[.*\]))\s*\)/' => function ($m) {
                $name = $m[1];
                $props = $m[2] ?? '[]';
                return "<?php echo app(Kailyn\\Component\\ComponentManager::class)->component('{$name}', {$props}); ?>";
            },
            '/@component\s*\(\s*"([^"]+)"\s*(?:,\s*(\[.*\]))\s*\)/' => function ($m) {
                $name = $m[1];
                $props = $m[2] ?? '[]';
                return "<?php echo app(Kailyn\\Component\\ComponentManager::class)->component('{$name}', {$props}); ?>";
            },
            '/@component\s*\(\s*\'([^\']+)\'\s*\)/' => function ($m) {
                return "<?php echo app(Kailyn\\Component\\ComponentManager::class)->component('{$m[1]}'); ?>";
            },
            '/@component\s*\(\s*"([^"]+)"\s*\)/' => function ($m) {
                return "<?php echo app(Kailyn\\Component\\ComponentManager::class)->component('{$m[1]}'); ?>";
            },
        ];

        foreach ($pregCallbacks as $pattern => $callback) {
            $template = preg_replace_callback($pattern, $callback, $template);
        }

        $stringDirectives = [
            '@endcomponent' => '',
            '@csrf' => '<?php echo csrf_field(); ?>',
        ];

        foreach ($stringDirectives as $search => $replace) {
            $template = str_replace($search, $replace, $template);
        }

        return $template;
    }

    protected function compilePhpBlocks(string $template): string
    {
        $template = preg_replace('/@php\s*/', '<?php ', $template);
        $template = preg_replace('/@endphp/', ' ?>', $template);

        return $template;
    }

    public function directive(string $name, callable $handler): void
    {
        $this->directives[$name] = $handler;
    }

    public function getDirectives(): array
    {
        return $this->directives;
    }
}
