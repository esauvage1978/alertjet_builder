import { Fragment } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useBootstrap } from '../../context/BootstrapContext.jsx';

const STEP_PATHS = ['/initialisation/organisation', '/initialisation/plan', '/initialisation/profil', '/initialisation/projet'];

function stepLabels(i18n) {
  return [i18n.wizard_steps_organization, i18n.wizard_steps_plan, i18n.wizard_steps_profile, i18n.wizard_steps_project];
}

export default function SetupWizardStepper() {
  const { data } = useBootstrap();
  const { pathname } = useLocation();
  const { i18n } = data;

  if (!pathname.startsWith('/initialisation')) {
    return null;
  }

  const labels = stepLabels(i18n);
  const currentIdx = STEP_PATHS.findIndex((p) => pathname === p);
  if (currentIdx < 0) {
    return null;
  }

  const steps = STEP_PATHS.map((path, i) => ({ path, label: labels[i] ?? `Step ${i + 1}` }));

  return (
    <nav className="setup-wizard-stepper" aria-label={i18n.wizard_steps_progress_aria}>
      <div className="setup-wizard-stepper__scroll">
        <div className="setup-wizard-stepper__track">
          {steps.map((step, i) => {
            const isComplete = i < currentIdx;
            const isCurrent = i === currentIdx;
            const segmentClass = [
              'setup-wizard-stepper__segment',
              isComplete && 'is-complete',
              isCurrent && 'is-current',
              !isComplete && !isCurrent && 'is-upcoming',
            ]
              .filter(Boolean)
              .join(' ');

            const marker = isComplete ? (
              <span className="setup-wizard-stepper__marker" aria-hidden="true">
                <i className="fas fa-check setup-wizard-stepper__check" />
              </span>
            ) : (
              <span className="setup-wizard-stepper__marker" aria-hidden="true">
                {i + 1}
              </span>
            );

            const labelEl = <span className="setup-wizard-stepper__label">{step.label}</span>;

            return (
              <Fragment key={step.path}>
                <div className="setup-wizard-stepper__node-col">
                  <div className={segmentClass}>
                    {isComplete ? (
                      <Link to={step.path} className="setup-wizard-stepper__link" aria-label={step.label}>
                        {marker}
                        {labelEl}
                      </Link>
                    ) : isCurrent ? (
                      <div className="setup-wizard-stepper__static" aria-current="step">
                        {marker}
                        {labelEl}
                      </div>
                    ) : (
                      <div className="setup-wizard-stepper__static setup-wizard-stepper__static--muted">
                        {marker}
                        {labelEl}
                      </div>
                    )}
                  </div>
                </div>
                {i < steps.length - 1 ? (
                  <span
                    className={`setup-wizard-stepper__connector${currentIdx > i ? ' is-done' : ''}`}
                    aria-hidden="true"
                  />
                ) : null}
              </Fragment>
            );
          })}
        </div>
      </div>
    </nav>
  );
}
