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

namespace NetFusion\Core\Kube\Controller;

use NetFusion\Core\Kube\Enums\PodPhase;
use NetFusion\Core\Kube\Models\Deployment;
use NetFusion\Core\Kube\Models\Node;
use NetFusion\Core\Kube\Models\Pod;
use NetFusion\Core\Kube\Network\IpamManager;
use NetFusion\Core\Kube\Parser\ManifestParser;
use NetFusion\Core\Kube\Scheduler\PodScheduler;

/**
 * Reconciles Deployments: Desired state vs actual state, scaling up/down and rolling updates.
 */
class DeploymentController
{
    public function __construct(
        protected PodScheduler $scheduler,
        protected PodLifecycleController $lifecycle,
        protected IpamManager $ipam,
        protected ?ManifestParser $manifestParser = null
    ) {
        $this->manifestParser ??= new ManifestParser();
    }

    /**
     * Reconcile a single Deployment against cluster state.
     *
     * @param Deployment $deployment
     * @param Pod[] $clusterPods
     * @param Node[] $nodes
     * @return array{created: Pod[], terminated: Pod[]} Newly created and terminated pods
     */
    public function reconcile(Deployment $deployment, array &$clusterPods, array $nodes): array
    {
        $matchingPods = [];
        foreach ($clusterPods as $key => $pod) {
            if ($deployment->matchesPod($pod) && !$pod->isTerminal()) {
                $matchingPods[$key] = $pod;
            }
        }

        $currentCount = count($matchingPods);
        $desiredCount = $deployment->getReplicas();
        $created = [];
        $terminated = [];

        if ($currentCount < $desiredCount) {
            // Scale Up
            $deficit = $desiredCount - $currentCount;
            for ($i = 0; $i < $deficit; $i++) {
                $newPod = $this->createPodFromTemplate($deployment);
                $this->scheduler->schedule($newPod, $nodes);
                $this->ipam->allocateIp($newPod);
                $this->lifecycle->startPod($newPod);

                $clusterPods[] = $newPod;
                $created[] = $newPod;
            }
        } elseif ($currentCount > $desiredCount) {
            // Scale Down
            $surplus = $currentCount - $desiredCount;
            $podsToKill = array_slice($matchingPods, -$surplus, $surplus, true);

            foreach ($podsToKill as $index => $pod) {
                // Find node and release resources
                $nodeName = $pod->getNodeName();
                if ($nodeName !== null) {
                    foreach ($nodes as $node) {
                        if ($node->getName() === $nodeName) {
                            $node->release($pod->getTotalResources(), $pod->getName());
                            break;
                        }
                    }
                }

                $this->ipam->releaseIp($pod);
                $this->lifecycle->terminatePod($pod, 0, 'ScaledDown');
                unset($clusterPods[$index]);
                $terminated[] = $pod;
            }
            $clusterPods = array_values($clusterPods);
        }


        // Update deployment telemetry
        $ready = 0;
        $active = 0;
        foreach ($clusterPods as $pod) {
            if ($deployment->matchesPod($pod) && !$pod->isTerminal()) {
                $active++;
                if ($pod->isReady()) {
                    $ready++;
                }
            }
        }
        $deployment->setReadyReplicas($ready);
        $deployment->setAvailableReplicas($active);

        return [
            'created'    => $created,
            'terminated' => $terminated,
        ];
    }

    /**
     * Synthesize a new Pod instance from the Deployment's template specification.
     */
    protected function createPodFromTemplate(Deployment $deployment): Pod
    {
        $template = $deployment->getTemplate();
        $randomSuffix = bin2hex(random_bytes(3));
        $podName = "{$deployment->getName()}-{$randomSuffix}";

        $podData = [
            'kind'     => 'Pod',
            'metadata' => [
                'name'      => $podName,
                'namespace' => $deployment->getNamespace(),
                'labels'    => array_merge(
                    $deployment->getMatchLabels(),
                    $template['metadata']['labels'] ?? []
                ),
            ],
            'spec'     => $template['spec'] ?? [],
        ];

        return $this->manifestParser->buildPod($podData);
    }
}
