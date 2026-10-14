<?php

declare(strict_types=1);

/**
 * |--------------------------------------------------------------------------
 * | NetFusion Framework
 * |--------------------------------------------------------------------------
 * |
 * | Author    : Md. Atiquzzaman Ashish
 * | Company   : DotNetBD
 * | Website   : www.dotnetbd.com
 * | Copyright : © DotNetBD. All rights reserved.
 * | License   : MIT
 * | Version   : 1.0.0
 * |
 * |--------------------------------------------------------------------------
 */

namespace NetFusion\Core\Kube\Parser;

use InvalidArgumentException;
use NetFusion\Core\Kube\Enums\PodPhase;
use NetFusion\Core\Kube\Enums\ProbeType;
use NetFusion\Core\Kube\Enums\RestartPolicy;
use NetFusion\Core\Kube\Enums\ServiceType;
use NetFusion\Core\Kube\Enums\TaintEffect;
use NetFusion\Core\Kube\Models\ContainerSpec;
use NetFusion\Core\Kube\Models\Deployment;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Models\ProbeConfig;
use NetFusion\Core\Kube\Models\ResourceRequirements;
use NetFusion\Core\Kube\Models\Service;
use NetFusion\Core\Kube\Models\Taint;
use NetFusion\Core\Kube\Models\Toleration;
use NetFusion\Core\Kube\Models\VolumeMount;

/**
 * Parses declarative Kubernetes manifests (YAML or JSON) into typed domain objects.
 */
class ManifestParser
{
    /**
     * Parse raw YAML or JSON manifest string into an array of Kubernetes resource objects.
     *
     * @param string $manifest Raw manifest content or file path
     * @return object[] Array of Pod, Deployment, Service, or Node objects
     */
    public function parse(string $manifest): array
    {
        $content = $manifest;
        if (file_exists($manifest) && is_file($manifest)) {
            $content = (string) file_get_contents($manifest);
        }

        $docs = $this->splitDocuments($content);
        $resources = [];

        foreach ($docs as $doc) {
            $trimmed = trim($doc);
            if ($trimmed === '') {
                continue;
            }

            $data = $this->parseDocument($trimmed);
            if (!empty($data) && isset($data['kind'])) {
                $resources[] = $this->buildResource($data);
            }
        }

        return $resources;
    }

    /**
     * Split multi-document YAML content by '---'.
     *
     * @return string[]
     */
    protected function splitDocuments(string $content): array
    {
        // Split by lines that start with ---
        $parts = preg_split('/^---(?:\r?\n|$)/m', $content);
        return $parts ?: [$content];
    }

    /**
     * Parse an individual document (JSON or YAML format).
     *
     * @return array<string, mixed>
     */
    protected function parseDocument(string $doc): array
    {
        // If it starts with { it is JSON
        if (str_starts_with($doc, '{')) {
            $json = json_decode($doc, true);
            if (is_array($json)) {
                return $json;
            }
        }

        return $this->parseSimpleYaml($doc);
    }

    /**
     * Zero-dependency recursive line-based YAML parser for Kubernetes manifests.
     *
     * @return array<string, mixed>
     */
    public function parseSimpleYaml(string $yaml): array
    {
        $lines = preg_split('/\r?\n/', $yaml);
        $result = [];
        $stack = [&$result];
        $indents = [-1];

        // Convert YAML lines into structured nested arrays
        return $this->parseYamlLines($lines);
    }

    /**
     * Parse array of YAML lines into nested PHP array structure.
     *
     * @param string[] $lines
     * @return array<string, mixed>
     */
    protected function parseYamlLines(array $lines): array
    {
        $root = [];
        $cursor = &$root;
        $contextStack = [];

        $cleanLines = [];
        foreach ($lines as $line) {
            // Strip comments
            $lineClean = preg_replace('/#.*$/', '', $line);
            if (trim($lineClean) === '') {
                continue;
            }
            $indent = strlen($lineClean) - strlen(ltrim($lineClean));
            $cleanLines[] = ['indent' => $indent, 'text' => trim($lineClean)];
        }

        return $this->buildTree($cleanLines, 0)[0];
    }

    /**
     * Recursively build array tree from indented lines.
     *
     * @param array<int, array{indent: int, text: string}> $lines
     * @param int $startIndex
     * @return array{0: array<string, mixed>, 1: int}
     */
    protected function buildTree(array $lines, int $startIndex): array
    {
        $result = [];
        $count = count($lines);
        $i = $startIndex;
        $baseIndent = $lines[$startIndex]['indent'] ?? 0;
        $isList = false;

        while ($i < $count) {
            $item = $lines[$i];
            $indent = $item['indent'];
            $text = $item['text'];

            if ($indent < $baseIndent) {
                break;
            }

            if (str_starts_with($text, '- ')) {
                $isList = true;
                $valuePart = trim(substr($text, 2));

                if (str_contains($valuePart, ':')) {
                    // List item with an inline key-value (e.g. - name: my-container)
                    [$k, $v] = explode(':', $valuePart, 2);
                    $k = trim($k);
                    $v = trim($v);

                    $subItem = [];
                    if ($v !== '') {
                        $subItem[$k] = $this->castValue($v);
                    }

                    // Check if subsequent lines belong to this list item
                    if ($i + 1 < $count && $lines[$i + 1]['indent'] > $indent) {
                        [$children, $nextI] = $this->buildTree($lines, $i + 1);
                        $subItem = array_merge($subItem, $children);
                        $i = $nextI - 1;
                    }

                    $result[] = $subItem;
                } else {
                    // Scalar list item or list of complex objects
                    if ($valuePart !== '') {
                        $result[] = $this->castValue($valuePart);
                    } elseif ($i + 1 < $count && $lines[$i + 1]['indent'] > $indent) {
                        [$children, $nextI] = $this->buildTree($lines, $i + 1);
                        $result[] = $children;
                        $i = $nextI - 1;
                    }
                }
            } elseif (str_contains($text, ':')) {
                [$key, $val] = explode(':', $text, 2);
                $key = trim($key);
                $val = trim($val);

                if ($val === '') {
                    // Nested block
                    if ($i + 1 < $count && $lines[$i + 1]['indent'] > $indent) {
                        [$children, $nextI] = $this->buildTree($lines, $i + 1);
                        $result[$key] = $children;
                        $i = $nextI - 1;
                    } else {
                        $result[$key] = null;
                    }
                } else {
                    $result[$key] = $this->castValue($val);
                }
            }

            $i++;
        }

        return [$result, $i];
    }

    /**
     * Cast raw YAML string value to PHP native scalar type.
     */
    protected function castValue(string $val): mixed
    {
        $trimmed = trim($val);

        // Quoted string
        if ((str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) ||
            (str_starts_with($trimmed, "'") && str_ends_with($trimmed, "'"))) {
            return substr($trimmed, 1, -1);
        }

        // Inline JSON array or object
        if ((str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) ||
            (str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))) {
            $decoded = json_decode($trimmed, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        $lower = strtolower($trimmed);
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null' || $lower === '~') {
            return null;
        }
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $trimmed;
    }

    /**
     * Build appropriate domain resource from parsed manifest data.
     *
     * @param array<string, mixed> $data
     */
    public function buildResource(array $data): object
    {
        $kind = $data['kind'] ?? '';
        return match ($kind) {
            'Pod'        => $this->buildPod($data),
            'Deployment' => $this->buildDeployment($data),
            'Service'    => $this->buildService($data),
            'Node'       => $this->buildNode($data),
            default      => throw new InvalidArgumentException("Unsupported Kubernetes resource kind: '{$kind}'"),
        };
    }

    /**
     * Build a Pod object from parsed data.
     *
     * @param array<string, mixed> $data
     */
    public function buildPod(array $data): Pod
    {
        $metadata = $data['metadata'] ?? [];
        $spec = $data['spec'] ?? [];

        $name = (string) ($metadata['name'] ?? 'unnamed-pod');
        $namespace = (string) ($metadata['namespace'] ?? 'default');
        $labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];
        $annotations = is_array($metadata['annotations'] ?? null) ? $metadata['annotations'] : [];

        $restartPolicyStr = $spec['restartPolicy'] ?? 'Always';
        $restartPolicy = RestartPolicy::tryFrom($restartPolicyStr) ?? RestartPolicy::ALWAYS;

        $nodeSelector = is_array($spec['nodeSelector'] ?? null) ? $spec['nodeSelector'] : [];
        $tolerations = $this->buildTolerations($spec['tolerations'] ?? []);
        $volumes = is_array($spec['volumes'] ?? null) ? $spec['volumes'] : [];

        $containers = [];
        foreach ($spec['containers'] ?? [] as $c) {
            $containers[] = $this->buildContainerSpec($c);
        }

        return new Pod(
            name: $name,
            namespace: $namespace,
            labels: $labels,
            annotations: $annotations,
            nodeName: $spec['nodeName'] ?? null,
            phase: PodPhase::PENDING,
            containers: $containers,
            restartPolicy: $restartPolicy,
            nodeSelector: $nodeSelector,
            tolerations: $tolerations,
            volumes: $volumes
        );
    }

    /**
     * Build a ContainerSpec from parsed container spec array.
     *
     * @param array<string, mixed> $c
     */
    protected function buildContainerSpec(array $c): ContainerSpec
    {
        $name = (string) ($c['name'] ?? 'app');
        $image = (string) ($c['image'] ?? 'scratch');
        $command = is_array($c['command'] ?? null) ? $c['command'] : [];
        $args = is_array($c['args'] ?? null) ? $c['args'] : [];

        // Ports
        $ports = [];
        foreach ($c['ports'] ?? [] as $p) {
            if (is_array($p) && isset($p['containerPort'])) {
                $ports[] = (int) $p['containerPort'];
            } elseif (is_numeric($p)) {
                $ports[] = (int) $p;
            }
        }

        // Environment variables
        $env = [];
        foreach ($c['env'] ?? [] as $e) {
            if (is_array($e) && isset($e['name'])) {
                $env[$e['name']] = (string) ($e['value'] ?? '');
            } elseif (is_string($e)) {
                $env[$e] = '';
            }
        }

        // Resources
        $resources = null;
        if (isset($c['resources']['requests']) || isset($c['resources']['limits'])) {
            $req = $c['resources']['requests'] ?? $c['resources']['limits'] ?? [];
            $cpu = $req['cpu'] ?? '100m';
            $mem = $req['memory'] ?? '128Mi';
            $resources = ResourceRequirements::parse($cpu, $mem);
        }

        // Probes
        $liveness = isset($c['livenessProbe']) ? $this->buildProbe(ProbeType::LIVENESS, $c['livenessProbe']) : null;
        $readiness = isset($c['readinessProbe']) ? $this->buildProbe(ProbeType::READINESS, $c['readinessProbe']) : null;
        $startup = isset($c['startupProbe']) ? $this->buildProbe(ProbeType::STARTUP, $c['startupProbe']) : null;

        // VolumeMounts
        $volumeMounts = [];
        foreach ($c['volumeMounts'] ?? [] as $vm) {
            if (is_array($vm) && isset($vm['name'], $vm['mountPath'])) {
                $volumeMounts[] = new VolumeMount(
                    (string) $vm['name'],
                    (string) $vm['mountPath'],
                    (bool) ($vm['readOnly'] ?? false),
                    $vm['subPath'] ?? null
                );
            }
        }

        return new ContainerSpec(
            name: $name,
            image: $image,
            command: $command,
            args: $args,
            env: $env,
            ports: $ports,
            resources: $resources,
            livenessProbe: $liveness,
            readinessProbe: $readiness,
            startupProbe: $startup,
            volumeMounts: $volumeMounts
        );
    }

    /**
     * Build ProbeConfig from probe definition.
     *
     * @param array<string, mixed> $probeData
     */
    protected function buildProbe(ProbeType $type, array $probeData): ProbeConfig
    {
        $mechanism = 'httpGet';
        $spec = [];

        if (isset($probeData['httpGet'])) {
            $mechanism = 'httpGet';
            $spec = is_array($probeData['httpGet']) ? $probeData['httpGet'] : [];
        } elseif (isset($probeData['tcpSocket'])) {
            $mechanism = 'tcpSocket';
            $spec = is_array($probeData['tcpSocket']) ? $probeData['tcpSocket'] : [];
        } elseif (isset($probeData['exec'])) {
            $mechanism = 'exec';
            $spec = is_array($probeData['exec']) ? $probeData['exec'] : [];
        }

        return new ProbeConfig(
            type: $type,
            mechanism: $mechanism,
            spec: $spec,
            initialDelaySeconds: (int) ($probeData['initialDelaySeconds'] ?? 0),
            periodSeconds: (int) ($probeData['periodSeconds'] ?? 10),
            timeoutSeconds: (int) ($probeData['timeoutSeconds'] ?? 1),
            successThreshold: (int) ($probeData['successThreshold'] ?? 1),
            failureThreshold: (int) ($probeData['failureThreshold'] ?? 3)
        );
    }

    /**
     * Build Tolerations list.
     *
     * @param array<mixed> $tolerationData
     * @return Toleration[]
     */
    protected function buildTolerations(array $tolerationData): array
    {
        $tolerations = [];
        foreach ($tolerationData as $t) {
            if (is_array($t) && isset($t['key'])) {
                $effect = isset($t['effect']) ? TaintEffect::tryFrom($t['effect']) : null;
                $tolerations[] = new Toleration(
                    key: (string) $t['key'],
                    operator: (string) ($t['operator'] ?? 'Equal'),
                    value: $t['value'] ?? null,
                    effect: $effect,
                    tolerationSeconds: isset($t['tolerationSeconds']) ? (int) $t['tolerationSeconds'] : null
                );
            }
        }
        return $tolerations;
    }

    /**
     * Build Deployment object.
     *
     * @param array<string, mixed> $data
     */
    public function buildDeployment(array $data): Deployment
    {
        $metadata = $data['metadata'] ?? [];
        $spec = $data['spec'] ?? [];

        $name = (string) ($metadata['name'] ?? 'unnamed-deployment');
        $namespace = (string) ($metadata['namespace'] ?? 'default');
        $replicas = (int) ($spec['replicas'] ?? 1);

        $matchLabels = [];
        if (isset($spec['selector']['matchLabels']) && is_array($spec['selector']['matchLabels'])) {
            $matchLabels = $spec['selector']['matchLabels'];
        }

        $template = is_array($spec['template'] ?? null) ? $spec['template'] : [];
        $strategy = (string) ($spec['strategy']['type'] ?? 'RollingUpdate');

        $maxSurge = $spec['strategy']['rollingUpdate']['maxSurge'] ?? '25%';
        $maxUnavailable = $spec['strategy']['rollingUpdate']['maxUnavailable'] ?? '25%';

        return new Deployment(
            name: $name,
            namespace: $namespace,
            replicas: $replicas,
            matchLabels: $matchLabels,
            template: $template,
            strategy: $strategy,
            maxSurge: $maxSurge,
            maxUnavailable: $maxUnavailable
        );
    }

    /**
     * Build Service object.
     *
     * @param array<string, mixed> $data
     */
    public function buildService(array $data): Service
    {
        $metadata = $data['metadata'] ?? [];
        $spec = $data['spec'] ?? [];

        $name = (string) ($metadata['name'] ?? 'unnamed-service');
        $namespace = (string) ($metadata['namespace'] ?? 'default');

        $typeStr = $spec['type'] ?? 'ClusterIP';
        $type = ServiceType::tryFrom($typeStr) ?? ServiceType::CLUSTER_IP;

        $clusterIp = $spec['clusterIP'] ?? null;
        $selector = is_array($spec['selector'] ?? null) ? $spec['selector'] : [];

        $ports = [];
        foreach ($spec['ports'] ?? [] as $p) {
            if (is_array($p) && isset($p['port'])) {
                $ports[] = [
                    'name'       => $p['name'] ?? null,
                    'port'       => (int) $p['port'],
                    'targetPort' => (int) ($p['targetPort'] ?? $p['port']),
                    'protocol'   => $p['protocol'] ?? 'TCP',
                    'nodePort'   => isset($p['nodePort']) ? (int) $p['nodePort'] : null,
                ];
            }
        }

        return new Service(
            name: $name,
            namespace: $namespace,
            type: $type,
            clusterIp: $clusterIp,
            ports: $ports,
            selector: $selector
        );
    }

    /**
     * Build Node object.
     *
     * @param array<string, mixed> $data
     */
    public function buildNode(array $data): Node
    {
        $metadata = $data['metadata'] ?? [];
        $status = $data['status'] ?? [];

        $name = (string) ($metadata['name'] ?? 'unnamed-node');
        $labels = is_array($metadata['labels'] ?? null) ? $metadata['labels'] : [];

        // Taints
        $taints = [];
        foreach ($data['spec']['taints'] ?? [] as $t) {
            if (is_array($t) && isset($t['key'])) {
                $effect = isset($t['effect']) ? TaintEffect::tryFrom($t['effect']) ?? TaintEffect::NO_SCHEDULE : TaintEffect::NO_SCHEDULE;
                $taints[] = new Taint($t['key'], $t['value'] ?? null, $effect);
            }
        }

        // Capacity
        $capacity = null;
        if (isset($status['capacity'])) {
            $cpu = $status['capacity']['cpu'] ?? '4';
            $mem = $status['capacity']['memory'] ?? '8Gi';
            $capacity = ResourceRequirements::parse($cpu, $mem);
        }

        // Allocatable
        $allocatable = null;
        if (isset($status['allocatable'])) {
            $cpu = $status['allocatable']['cpu'] ?? '3800m';
            $mem = $status['allocatable']['memory'] ?? '7680Mi';
            $allocatable = ResourceRequirements::parse($cpu, $mem);
        }

        return new Node(
            name: $name,
            internalIp: $status['addresses'][0]['address'] ?? '10.0.0.1',
            status: 'Ready',
            labels: $labels,
            taints: $taints,
            capacity: $capacity,
            allocatable: $allocatable
        );
    }
}
