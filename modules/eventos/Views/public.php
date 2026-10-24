<?php

declare(strict_types=1);
$months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
$weekdays = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
$grouped = [];
foreach ($events as $event) {
    $date = (new DateTimeImmutable((string) $event['inicio']))->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $grouped[$date->format('Y-m')]['label'] = ucfirst($months[(int) $date->format('n') - 1]) . ' ' . $date->format('Y');
    $grouped[$date->format('Y-m')]['events'][] = [$event, $date];
}
?>
<main class="public-events-main">
    <section class="events-hero"><div class="events-container"><p class="eyebrow"><span class="eyebrow-line"></span> AGENDA DA EQUIPE</p><h1>O próximo jogo<br><span>começa aqui.</span></h1><p>Acompanhe jogos, treinos, amistosos e compromissos do Sistema FLAG.</p></div></section>
    <section class="events-container events-calendar" aria-label="Agenda de eventos">
        <?php if ($grouped === []): ?><div class="events-empty"><span aria-hidden="true">◷</span><h2>A agenda está sendo preparada</h2><p>Em breve, os próximos eventos da equipe estarão aqui.</p></div>
        <?php else: foreach ($grouped as $month): ?><section class="events-month"><h2><?= $escape($month['label']) ?></h2><div class="events-timeline">
            <?php foreach ($month['events'] as [$event, $date]): $kind = ['jogo'=>'Jogo','treino'=>'Treino','amistoso'=>'Amistoso','compromisso'=>'Compromisso'][(string) $event['tipo']] ?? 'Evento'; $hasReleaseImage = in_array($event['tem_release_imagem'] ?? false, [true, 1, '1', 't', 'true'], true); $releaseEnabled = in_array($event['release_ativo'] ?? false, [true, 1, '1', 't', 'true'], true); ?>
                <article class="event-card event-card-<?= $escape($event['tipo']) ?> <?= $event['status'] === 'cancelado' ? 'event-card-cancelled' : '' ?>">
                    <div class="event-date"><strong><?= $date->format('d') ?></strong><span><?= $escape($weekdays[(int) $date->format('w')]) ?></span></div>
                    <div class="event-content"><div class="event-card-top"><span class="event-kind event-kind-<?= $escape($event['tipo']) ?>"><?= $escape($kind) ?></span><?php if ($event['status'] === 'cancelado'): ?><span class="event-status">Cancelado</span><?php elseif ($event['status'] === 'concluido'): ?><span class="event-status">Realizado</span><?php endif; ?></div>
                        <h3><?= $escape($event['titulo']) ?></h3><?php if (!empty($event['categoria_modalidade'])): ?><p class="event-category"><?= $escape($event['categoria_modalidade']) ?> · <?= $event['categoria_genero'] === 'feminino' ? 'Feminino' : 'Masculino' ?></p><?php endif; ?><p class="event-time"><?= $date->format('H:i') ?><?= $event['fim'] ? ' – ' . (new DateTimeImmutable((string) $event['fim']))->setTimezone(new DateTimeZone('America/Sao_Paulo'))->format('H:i') : '' ?><?= !empty($event['local']) ? ' · ' . $escape($event['local']) : '' ?></p>
                        <?php if (in_array($event['tipo'], ['jogo','amistoso'], true)): ?>
                            <?php if ($event['placar_nos'] !== null && $event['placar_adversario'] !== null): ?><div class="event-score"><span>Sistema FLAG</span><strong><?= (int) $event['placar_nos'] ?> <i>—</i> <?= (int) $event['placar_adversario'] ?></strong><span><?= $escape($event['adversario'] ?: 'Adversário') ?></span></div><?php elseif ($event['adversario']): ?><p class="event-opponent">Contra <strong><?= $escape($event['adversario']) ?></strong></p><?php endif; ?>
                            <?php if ($event['atleta_destaque']): ?><p class="event-highlight">Destaque: <strong><?= $escape($event['atleta_destaque']) ?></strong></p><?php endif; ?>
                        <?php endif; ?>
                        <p class="event-attendance"><span aria-hidden="true">✓</span> <?= (int) $event['confirmados'] ?> <?= (int) $event['confirmados'] === 1 ? 'presença confirmada' : 'presenças confirmadas' ?></p>
                        <?php if ($releaseEnabled && ($event['release_texto'] || $hasReleaseImage)): ?><div class="event-release"><?php if ($event['release_texto']): ?><p><?= nl2br($escape($event['release_texto'])) ?></p><?php endif; ?><?php if ($hasReleaseImage): ?><img src="evento-imagem-<?= (int) $event['id'] ?>" alt="Imagem do evento <?= $escape($event['titulo']) ?>" width="900" height="506" loading="lazy" decoding="async"><?php endif; ?></div><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div></section><?php endforeach; endif; ?>
    </section>
</main>
